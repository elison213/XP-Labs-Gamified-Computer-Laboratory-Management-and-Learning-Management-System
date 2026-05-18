<?php
/**
 * XPLabs - PC Service
 * Manages lab PC inventory, sessions, drive mappings, folder rules, and remote commands.
 */

namespace XPLabs\Services;

use XPLabs\Lib\Database;

class PCService
{
    private Database $db;
    private ?bool $hasDiscoveryColumns = null;
    private ?bool $hasAutoDeployColumns = null;
    private int $heartbeatOfflineThresholdSeconds = 300;
    private ?bool $protocolDebugEnabled = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function isProtocolDebugEnabled(): bool
    {
        if ($this->protocolDebugEnabled !== null) {
            return $this->protocolDebugEnabled;
        }
        $cfg = require __DIR__ . '/../config/app.php';
        $raw = $cfg['pc_protocol_debug']['enabled'] ?? true;
        $this->protocolDebugEnabled = (bool) $raw;
        return $this->protocolDebugEnabled;
    }

    public function emitProtocolDebugEvent(?int $pcId, string $eventType, string $severity = 'info', array $payload = []): void
    {
        if (!$this->isProtocolDebugEnabled()) {
            return;
        }
        if (!in_array($severity, ['debug', 'info', 'warn', 'error'], true)) {
            $severity = 'info';
        }
        try {
            $this->db->insert('pc_protocol_debug_events', [
                'pc_id' => $pcId,
                'event_type' => $eventType,
                'severity' => $severity,
                'event_payload' => json_encode($payload),
            ]);
        } catch (\Throwable $e) {
            // Do not fail main flow on debug write errors.
        }
    }

    public function pruneProtocolDebugEvents(int $retentionDays = 14): int
    {
        $cfg = require __DIR__ . '/../config/app.php';
        $cfgDays = (int) ($cfg['pc_protocol_debug']['retention_days'] ?? 14);
        if ($cfgDays > 0) {
            $retentionDays = $cfgDays;
        }
        if ($retentionDays < 1) {
            $retentionDays = 1;
        }
        try {
            $stmt = $this->db->query(
                "DELETE FROM pc_protocol_debug_events WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$retentionDays]
            );
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function listProtocolDebugEvents(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['pc_id'])) {
            $where[] = 'e.pc_id = ?';
            $params[] = (int) $filters['pc_id'];
        }
        if (!empty($filters['event_type'])) {
            $where[] = 'e.event_type = ?';
            $params[] = (string) $filters['event_type'];
        }
        if (!empty($filters['heartbeat_id'])) {
            $where[] = 'e.event_payload LIKE ?';
            $params[] = '%"heartbeat_id":"' . str_replace('"', '\"', (string) $filters['heartbeat_id']) . '"%';
        }
        if (!empty($filters['since'])) {
            $where[] = 'e.created_at >= ?';
            $params[] = (string) $filters['since'];
        }
        if (!empty($filters['until'])) {
            $where[] = 'e.created_at <= ?';
            $params[] = (string) $filters['until'];
        }

        $limit = max(1, min(500, (int) ($filters['limit'] ?? 100)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        $whereSql = implode(' AND ', $where);

        return $this->db->fetchAll(
            "SELECT e.id, e.pc_id, lp.hostname, e.event_type, e.severity, e.event_payload, e.created_at
             FROM pc_protocol_debug_events e
             LEFT JOIN lab_pcs lp ON lp.id = e.pc_id
             WHERE $whereSql
             ORDER BY e.id DESC
             LIMIT $limit OFFSET $offset",
            $params
        );
    }

    private function supportsDiscoveryColumns(): bool
    {
        if ($this->hasDiscoveryColumns !== null) {
            return $this->hasDiscoveryColumns;
        }
        $count = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'lab_pcs'
               AND column_name IN ('assignment_status','discovery_source','discovered_at','last_seen_at')"
        );
        $this->hasDiscoveryColumns = $count >= 4;
        return $this->hasDiscoveryColumns;
    }

    private function supportsAutoDeployColumns(): bool
    {
        if ($this->hasAutoDeployColumns !== null) {
            return $this->hasAutoDeployColumns;
        }
        $count = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'lab_pcs'
               AND column_name IN ('auto_deploy_enabled','deployment_status','deployment_reason','deployment_tag','last_deploy_attempt_at','last_deploy_error')"
        );
        $this->hasAutoDeployColumns = $count >= 6;
        return $this->hasAutoDeployColumns;
    }

    private function getAutoDeployConfig(): array
    {
        $cfg = require __DIR__ . '/../config/app.php';
        $raw = $cfg['pc_auto_deploy'] ?? [];
        return [
            'enabled' => (bool) ($raw['enabled'] ?? true),
            'allow_subnets' => is_array($raw['allow_subnets'] ?? null) ? $raw['allow_subnets'] : [],
            'deny_subnets' => is_array($raw['deny_subnets'] ?? null) ? $raw['deny_subnets'] : [],
            'deny_tags' => is_array($raw['deny_tags'] ?? null) ? $raw['deny_tags'] : [],
            'default_deny_unknown_networks' => (bool) ($raw['default_deny_unknown_networks'] ?? false),
            'max_bulk_jobs_per_request' => max(1, (int) ($raw['max_bulk_jobs_per_request'] ?? 25)),
            'max_parallel_jobs' => max(1, (int) ($raw['max_parallel_jobs'] ?? 5)),
        ];
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        if ($ip === '' || $cidr === '' || strpos($cidr, '/') === false) {
            return false;
        }
        [$subnet, $maskBits] = explode('/', $cidr, 2);
        $maskBits = (int) $maskBits;
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false || $maskBits < 0 || $maskBits > 32) {
            return false;
        }
        $mask = $maskBits === 0 ? 0 : (~((1 << (32 - $maskBits)) - 1));
        return (($ipLong & $mask) === ($subnetLong & $mask));
    }

    private function resolveEffectivePcStatus(array $pc): string
    {
        $status = strtolower(trim((string) ($pc['status'] ?? 'offline')));
        $heartbeatTs = strtotime((string) ($pc['last_heartbeat'] ?? ''));
        if ($heartbeatTs === false || (time() - $heartbeatTs) > $this->heartbeatOfflineThresholdSeconds) {
            return 'offline';
        }
        if (!in_array($status, ['online', 'idle', 'locked', 'offline', 'maintenance'], true)) {
            return 'offline';
        }
        return $status;
    }

    // =====================================================
    // PC Registration & Management
    // =====================================================

    /**
     * Register a new lab PC or return existing one.
     */
    public function registerPC(array $data): array
    {
        $hostname = $data['hostname'] ?? '';
        $ipAddress = $data['ip_address'] ?? null;
        $macAddress = $data['mac_address'] ?? null;
        $floorId = isset($data['floor_id']) ? (int) $data['floor_id'] : null;
        $stationId = isset($data['station_id']) ? (int) $data['station_id'] : null;
        if ($floorId !== null && $floorId > 0) {
            $floorExists = (int) $this->db->fetchOne("SELECT COUNT(*) FROM lab_floors WHERE id = ?", [$floorId]);
            if ($floorExists === 0) {
                $floorId = null;
            }
        } else {
            $floorId = null;
        }
        if ($stationId !== null && $stationId > 0) {
            $stationExists = (int) $this->db->fetchOne("SELECT COUNT(*) FROM lab_stations WHERE id = ?", [$stationId]);
            if ($stationExists === 0) {
                $stationId = null;
            }
        } else {
            $stationId = null;
        }

        if (empty($hostname)) {
            return ['success' => false, 'error' => 'Hostname is required'];
        }

        // Check if PC already exists
        $existing = $this->db->fetch("SELECT * FROM lab_pcs WHERE hostname = ?", [$hostname]);

        if ($existing) {
            // Update existing PC. Do not wipe floor/station assignment when agent sends null.
            $update = [
                'ip_address' => $ipAddress,
                'mac_address' => $macAddress,
                'status' => 'online',
                'last_heartbeat' => date('Y-m-d H:i:s'),
                'last_seen_at' => date('Y-m-d H:i:s'),
            ];
            if ($floorId !== null) {
                $update['floor_id'] = $floorId;
            }
            if ($stationId !== null) {
                $update['station_id'] = $stationId;
            }
            if ($this->supportsDiscoveryColumns()) {
                $hasAssignment = ($update['floor_id'] ?? $existing['floor_id'] ?? null) || ($update['station_id'] ?? $existing['station_id'] ?? null);
                $update['assignment_status'] = $hasAssignment ? 'assigned' : 'unassigned';
                $update['discovery_source'] = $existing['discovery_source'] ?: 'agent';
            }
            if ($this->supportsAutoDeployColumns()) {
                $update['deployment_status'] = $existing['deployment_status'] ?: 'pending';
                $update['deployment_reason'] = $existing['deployment_reason'] ?: 'Awaiting deployment policy evaluation';
            }
            $this->db->update('lab_pcs', $update, 'hostname = ?', [$hostname]);

            return [
                'success' => true,
                'pc_id' => $existing['id'],
                'machine_key' => $existing['machine_key'],
                'message' => 'PC already registered',
            ];
        }

        // Generate machine key
        $machineKey = bin2hex(random_bytes(32));

        $payload = [
            'hostname' => $hostname,
            'ip_address' => $ipAddress,
            'mac_address' => $macAddress,
            'floor_id' => $floorId,
            'station_id' => $stationId,
            'machine_key' => $machineKey,
            'status' => 'online',
            'last_heartbeat' => date('Y-m-d H:i:s'),
        ];
        if ($this->supportsDiscoveryColumns()) {
            $payload['assignment_status'] = ($floorId || $stationId) ? 'assigned' : 'unassigned';
            $payload['discovery_source'] = 'agent';
            $payload['discovered_at'] = date('Y-m-d H:i:s');
            $payload['last_seen_at'] = date('Y-m-d H:i:s');
        }
        if ($this->supportsAutoDeployColumns()) {
            $payload['auto_deploy_enabled'] = 1;
            $payload['deployment_status'] = 'pending';
            $payload['deployment_reason'] = 'Registered by agent; pending policy evaluation';
            $payload['deployment_tag'] = null;
            $payload['last_deploy_attempt_at'] = null;
            $payload['last_deploy_error'] = null;
        }
        $pcId = $this->db->insert('lab_pcs', $payload);

        return [
            'success' => true,
            'pc_id' => $pcId,
            'machine_key' => $machineKey,
            'message' => 'PC registered successfully',
        ];
    }

    /**
     * Get PC by hostname.
     */
    public function getPCByHostname(string $hostname): ?array
    {
        return $this->db->fetch("SELECT * FROM lab_pcs WHERE hostname = ?", [$hostname]);
    }

    /**
     * Get PC by ID.
     */
    public function getPCById(int $pcId): ?array
    {
        return $this->db->fetch("SELECT * FROM lab_pcs WHERE id = ?", [$pcId]);
    }

    /**
     * Get PC by machine key.
     */
    public function getPCByKey(string $machineKey): ?array
    {
        return $this->db->fetch("SELECT * FROM lab_pcs WHERE machine_key = ?", [$machineKey]);
    }

    /**
     * Update PC heartbeat timestamp.
     */
    public function updateHeartbeat(int $pcId): bool
    {
        $update = [
            'last_heartbeat' => date('Y-m-d H:i:s'),
            'status' => 'online',
        ];
        if ($this->supportsDiscoveryColumns()) {
            $update['last_seen_at'] = date('Y-m-d H:i:s');
        }
        $ok = $this->db->update('lab_pcs', $update, 'id = ?', [$pcId]) > 0;
        if ($ok) {
            $this->syncStationStatusFromPc($pcId);
        }
        return $ok;
    }

    public function processHeartbeatDelivery(array $pc, array $input): array
    {
        $startedAt = microtime(true);
        $pcId = (int) ($pc['id'] ?? 0);
        if ($pcId <= 0) {
            return ['success' => false, 'error' => 'Invalid PC context'];
        }

        $heartbeatId = trim((string) ($input['heartbeat_id'] ?? ''));
        $protocolVersion = trim((string) ($input['protocol_version'] ?? ''));
        if ($heartbeatId === '') {
            // Compatibility mode for v1 clients.
            $heartbeatId = 'legacy-' . bin2hex(random_bytes(8));
            if ($protocolVersion === '') {
                $protocolVersion = 'v1';
            }
        }
        if ($protocolVersion === '') {
            $protocolVersion = 'v2';
        }

        $status = strtolower(trim((string) ($input['status'] ?? 'online')));
        $systemInfo = $input['system_info'] ?? null;
        $cursor = (int) ($input['command_cursor'] ?? 0);
        if ($cursor < 0) {
            $cursor = 0;
        }

        $existing = $this->db->fetch(
            "SELECT id, response_json FROM pc_heartbeat_receipts WHERE pc_id = ? AND heartbeat_id = ? LIMIT 1",
            [$pcId, $heartbeatId]
        );
        if ($existing) {
            $payload = json_decode((string) $existing['response_json'], true);
            if (!is_array($payload)) {
                $payload = [];
            }
            $payload['duplicate'] = true;
            $payload['ack_id'] = (int) ($existing['id'] ?? 0);
            $this->emitProtocolDebugEvent($pcId, 'heartbeat_dedup_hit', 'info', [
                'pc_id' => $pcId,
                'heartbeat_id' => $heartbeatId,
                'ack_id' => (int) ($existing['id'] ?? 0),
                'request_cursor' => $cursor,
                'protocol_version' => $protocolVersion,
            ]);
            return $payload;
        }

        $this->emitProtocolDebugEvent($pcId, 'heartbeat_dedup_miss', 'info', [
            'pc_id' => $pcId,
            'heartbeat_id' => $heartbeatId,
            'request_cursor' => $cursor,
            'protocol_version' => $protocolVersion,
        ]);

        $this->updateHeartbeat($pcId);
        if (in_array($status, ['online', 'idle', 'locked', 'maintenance'], true)) {
            $this->updatePCStatus($pcId, $status);
        }
        if ($systemInfo !== null) {
            $config = json_decode((string) ($pc['config'] ?? '{}'), true) ?: [];
            $config['last_system_info'] = $systemInfo;
            $config['last_heartbeat_data'] = $input;
            $this->db->update('lab_pcs', ['config' => json_encode($config)], 'id = ?', [$pcId]);
        }

        $pendingCommands = $this->getPendingCommandsAfterCursor($pcId, $cursor);
        $activeSession = $this->getActiveSession($pcId);
        $nextCursor = $cursor;
        foreach ($pendingCommands as $cmd) {
            $cid = (int) ($cmd['id'] ?? 0);
            if ($cid > $nextCursor) {
                $nextCursor = $cid;
            }
        }

        $responsePayload = [
            'success' => true,
            'pc_id' => $pcId,
            'hostname' => (string) ($pc['hostname'] ?? ''),
            'duplicate' => false,
            'retry_after_sec' => 5,
            'command_cursor' => $nextCursor,
            'commands' => array_map(function ($cmd) {
                return [
                    'id' => (int) $cmd['id'],
                    'type' => $cmd['command_type'],
                    'params' => $cmd['params'] ? json_decode($cmd['params'], true) : null,
                    'issued_at' => $cmd['created_at'],
                    'expires_at' => $cmd['expires_at'],
                ];
            }, $pendingCommands),
            'active_session' => $activeSession ? [
                'session_id' => (int) $activeSession['id'],
                'user_id' => (int) $activeSession['user_id'],
                'user_name' => trim((string) $activeSession['first_name'] . ' ' . (string) $activeSession['last_name']),
                'lrn' => (string) $activeSession['lrn'],
                'checkin_time' => (string) $activeSession['checkin_time'],
            ] : null,
            'server_time' => date('Y-m-d H:i:s'),
        ];

        $responseJson = json_encode($responsePayload);
        $ackId = (int) $this->db->insert('pc_heartbeat_receipts', [
            'pc_id' => $pcId,
            'heartbeat_id' => $heartbeatId,
            'command_cursor' => $nextCursor,
            'response_json' => $responseJson ?: '{}',
        ]);
        $this->db->update('lab_pcs', [
            'last_heartbeat_ack_id' => $ackId,
            'last_command_cursor' => $nextCursor,
        ], 'id = ?', [$pcId]);
        $responsePayload['ack_id'] = $ackId;

        $this->trimHeartbeatReceipts($pcId, 250);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $this->emitProtocolDebugEvent($pcId, 'heartbeat_ack_issued', 'info', [
            'pc_id' => $pcId,
            'heartbeat_id' => $heartbeatId,
            'ack_id' => $ackId,
            'request_cursor' => $cursor,
            'next_cursor' => $nextCursor,
            'commands_count' => count($pendingCommands),
            'duplicate' => false,
            'protocol_version' => $protocolVersion,
            'latency_ms' => $latencyMs,
        ]);
        $this->pruneProtocolDebugEvents();

        return $responsePayload;
    }

    private function trimHeartbeatReceipts(int $pcId, int $keep): void
    {
        if ($keep < 1) {
            $keep = 1;
        }
        $boundary = $this->db->fetch(
            "SELECT id FROM pc_heartbeat_receipts WHERE pc_id = ? ORDER BY id DESC LIMIT 1 OFFSET ?",
            [$pcId, $keep - 1]
        );
        if (!$boundary || empty($boundary['id'])) {
            return;
        }
        $this->db->query(
            "DELETE FROM pc_heartbeat_receipts WHERE pc_id = ? AND id < ?",
            [$pcId, (int) $boundary['id']]
        );
    }

    public function syncStationStatusFromPc(int $pcId): void
    {
        $pc = $this->db->fetch("SELECT id, station_id, status, last_heartbeat FROM lab_pcs WHERE id = ?", [$pcId]);
        if (!$pc || empty($pc['station_id'])) {
            return;
        }
        $status = $this->resolveEffectivePcStatus($pc);
        if ($status === 'online' || $status === 'locked') {
            $hasActiveSession = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM pc_sessions WHERE pc_id = ? AND status = 'active'",
                [$pcId]
            ) > 0;
            $status = $hasActiveSession ? 'active' : 'idle';
        }
        if (!in_array($status, ['active', 'idle', 'offline', 'maintenance'], true)) {
            $status = 'offline';
        }
        $this->db->update('lab_stations', ['status' => $status], 'id = ?', [(int) $pc['station_id']]);
    }

    private function syncStationStatusFromStationId(int $stationId): void
    {
        $pc = $this->db->fetch(
            "SELECT id FROM lab_pcs WHERE station_id = ? ORDER BY COALESCE(last_heartbeat, updated_at) DESC LIMIT 1",
            [$stationId]
        );
        if ($pc) {
            $this->syncStationStatusFromPc((int) $pc['id']);
            return;
        }
        $this->db->update('lab_stations', ['status' => 'offline'], 'id = ?', [$stationId]);
    }

    /**
     * Upsert discovered host from DHCP/network scan.
     */
    public function upsertDiscoveredPc(array $host): array
    {
        $hostname = trim((string) ($host['hostname'] ?? ''));
        $ipAddress = trim((string) ($host['ip_address'] ?? ''));
        $macAddress = trim((string) ($host['mac_address'] ?? ''));
        if ($hostname === '' && $ipAddress === '' && $macAddress === '') {
            return ['success' => false, 'error' => 'No host identity provided'];
        }

        $existing = null;
        if ($macAddress !== '') {
            $existing = $this->db->fetch("SELECT * FROM lab_pcs WHERE mac_address = ? LIMIT 1", [$macAddress]);
        }
        if (!$existing && $hostname !== '') {
            $existing = $this->db->fetch("SELECT * FROM lab_pcs WHERE hostname = ? LIMIT 1", [$hostname]);
        }
        if (!$existing && $ipAddress !== '') {
            $existing = $this->db->fetch("SELECT * FROM lab_pcs WHERE ip_address = ? LIMIT 1", [$ipAddress]);
        }

        $now = date('Y-m-d H:i:s');
        if ($existing) {
            $update = [
                'hostname' => $hostname ?: $existing['hostname'],
                'ip_address' => $ipAddress ?: $existing['ip_address'],
                'mac_address' => $macAddress ?: $existing['mac_address'],
            ];
            if ($this->supportsDiscoveryColumns()) {
                $update['last_seen_at'] = $now;
                $update['discovery_source'] = 'dhcp';
                if (empty($existing['floor_id']) && empty($existing['station_id'])) {
                    $update['assignment_status'] = 'unassigned';
                }
            }
            $this->db->update('lab_pcs', $update, 'id = ?', [(int) $existing['id']]);
            return ['success' => true, 'pc_id' => (int) $existing['id'], 'updated' => true];
        }

        $machineKey = bin2hex(random_bytes(32));
        $payload = [
            'hostname' => $hostname ?: ("DISCOVERED-" . substr($machineKey, 0, 8)),
            'ip_address' => $ipAddress ?: null,
            'mac_address' => $macAddress ?: null,
            'machine_key' => $machineKey,
            'status' => 'offline',
            'last_heartbeat' => null,
        ];
        if ($this->supportsDiscoveryColumns()) {
            $payload['assignment_status'] = 'unassigned';
            $payload['discovery_source'] = 'dhcp';
            $payload['discovered_at'] = $now;
            $payload['last_seen_at'] = $now;
        }
        if ($this->supportsAutoDeployColumns()) {
            $payload['auto_deploy_enabled'] = 1;
            $payload['deployment_status'] = 'pending';
            $payload['deployment_reason'] = 'Discovered on network; pending policy evaluation';
            $payload['deployment_tag'] = null;
            $payload['last_deploy_attempt_at'] = null;
            $payload['last_deploy_error'] = null;
        }
        $pcId = $this->db->insert('lab_pcs', $payload);
        return ['success' => true, 'pc_id' => $pcId, 'created' => true];
    }

    public function getUnassignedPcs(): array
    {
        if (!$this->supportsDiscoveryColumns()) {
            return $this->db->fetchAll(
                "SELECT lp.* FROM lab_pcs lp
                 WHERE lp.floor_id IS NULL AND lp.station_id IS NULL
                 ORDER BY COALESCE(lp.last_heartbeat, lp.updated_at) DESC"
            );
        }
        return $this->db->fetchAll(
            "SELECT lp.*
             FROM lab_pcs lp
             WHERE (lp.assignment_status = 'unassigned' OR lp.assignment_status IS NULL)
               AND lp.floor_id IS NULL
               AND lp.station_id IS NULL
             ORDER BY COALESCE(lp.last_seen_at, lp.last_heartbeat, lp.updated_at) DESC"
        );
    }

    public function assignPc(int $pcId, int $floorId, ?int $stationId = null): bool
    {
        $existingPc = $this->db->fetch("SELECT station_id FROM lab_pcs WHERE id = ?", [$pcId]);
        $oldStationId = isset($existingPc['station_id']) ? (int) $existingPc['station_id'] : 0;

        if ($stationId) {
            $station = $this->db->fetch("SELECT id, floor_id FROM lab_stations WHERE id = ?", [$stationId]);
            if (!$station || (int) $station['floor_id'] !== $floorId) {
                throw new \RuntimeException('Station must belong to selected floor');
            }
            $occupied = (int) $this->db->fetchOne("SELECT COUNT(*) FROM lab_pcs WHERE station_id = ? AND id != ?", [$stationId, $pcId]);
            if ($occupied > 0) {
                throw new \RuntimeException('Station already has an assigned PC');
            }
        }
        $update = [
            'floor_id' => $floorId,
            'station_id' => $stationId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($this->supportsDiscoveryColumns()) {
            $update['assignment_status'] = 'assigned';
        }
        $ok = $this->db->update('lab_pcs', $update, 'id = ?', [$pcId]) > 0;
        if ($ok) {
            $this->syncStationStatusFromPc($pcId);
            if ($oldStationId > 0 && ($stationId === null || $oldStationId !== $stationId)) {
                $this->syncStationStatusFromStationId($oldStationId);
            }
        }
        return $ok;
    }

    public function unassignPc(int $pcId): bool
    {
        $existingPc = $this->db->fetch("SELECT station_id FROM lab_pcs WHERE id = ?", [$pcId]);
        $oldStationId = isset($existingPc['station_id']) ? (int) $existingPc['station_id'] : 0;

        $update = [
            'floor_id' => null,
            'station_id' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($this->supportsDiscoveryColumns()) {
            $update['assignment_status'] = 'unassigned';
        }
        $ok = $this->db->update('lab_pcs', $update, 'id = ?', [$pcId]) > 0;
        if ($ok && $oldStationId > 0) {
            $this->syncStationStatusFromStationId($oldStationId);
        }
        return $ok;
    }

    public function getUnassignedCount(): int
    {
        if (!$this->supportsDiscoveryColumns()) {
            return (int) $this->db->fetchOne("SELECT COUNT(*) FROM lab_pcs WHERE floor_id IS NULL AND station_id IS NULL");
        }
        return (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM lab_pcs
             WHERE (assignment_status = 'unassigned' OR assignment_status IS NULL)
               AND floor_id IS NULL
               AND station_id IS NULL"
        );
    }

    public function evaluateAutoDeployEligibility(array $pc): array
    {
        $cfg = $this->getAutoDeployConfig();
        if (!$cfg['enabled']) {
            return ['eligible' => false, 'status' => 'excluded', 'reason' => 'Auto deployment is disabled by config'];
        }
        if (!$this->supportsAutoDeployColumns()) {
            return ['eligible' => true, 'status' => 'pending', 'reason' => 'Auto deploy columns unavailable; default allow'];
        }

        $tag = strtolower(trim((string) ($pc['deployment_tag'] ?? '')));
        $autoDeployEnabled = (int) ($pc['auto_deploy_enabled'] ?? 1);
        $ip = trim((string) ($pc['ip_address'] ?? ''));

        if ($autoDeployEnabled === 0) {
            return ['eligible' => false, 'status' => 'excluded', 'reason' => 'Admin disabled auto deployment'];
        }
        if ($tag !== '' && in_array($tag, array_map('strtolower', $cfg['deny_tags']), true)) {
            return ['eligible' => false, 'status' => 'excluded', 'reason' => "Device tag '$tag' is denied by policy"];
        }
        foreach ($cfg['deny_subnets'] as $cidr) {
            if ($this->ipInCidr($ip, (string) $cidr)) {
                return ['eligible' => false, 'status' => 'excluded', 'reason' => "IP $ip is in denied subnet $cidr"];
            }
        }

        $allowSubnets = $cfg['allow_subnets'];
        if (!empty($allowSubnets)) {
            foreach ($allowSubnets as $cidr) {
                if ($this->ipInCidr($ip, (string) $cidr)) {
                    return ['eligible' => true, 'status' => 'pending', 'reason' => "IP $ip matches allowed subnet $cidr"];
                }
            }
            return ['eligible' => false, 'status' => 'excluded', 'reason' => "IP $ip does not match allowed subnets"];
        }

        if ($cfg['default_deny_unknown_networks'] && $ip === '') {
            return ['eligible' => false, 'status' => 'excluded', 'reason' => 'Unknown network and default deny enabled'];
        }

        return ['eligible' => true, 'status' => 'pending', 'reason' => 'Eligible by default policy'];
    }

    public function updateDeploymentPolicy(int $pcId, array $fields): bool
    {
        if (!$this->supportsAutoDeployColumns()) {
            return false;
        }
        $update = [];
        if (array_key_exists('auto_deploy_enabled', $fields)) {
            $update['auto_deploy_enabled'] = (int) ((bool) $fields['auto_deploy_enabled']);
        }
        if (array_key_exists('deployment_tag', $fields)) {
            $tag = trim((string) $fields['deployment_tag']);
            $update['deployment_tag'] = $tag !== '' ? $tag : null;
        }
        if (array_key_exists('deployment_status', $fields)) {
            $update['deployment_status'] = $fields['deployment_status'];
        }
        if (array_key_exists('deployment_reason', $fields)) {
            $update['deployment_reason'] = $fields['deployment_reason'];
        }
        if (empty($update)) {
            return false;
        }
        $update['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update('lab_pcs', $update, 'id = ?', [$pcId]) > 0;
    }

    public function applyAutoDeployPolicyToPc(int $pcId): array
    {
        $pc = $this->getPCById($pcId);
        if (!$pc) {
            return ['success' => false, 'error' => 'PC not found'];
        }
        $evaluation = $this->evaluateAutoDeployEligibility($pc);
        if ($this->supportsAutoDeployColumns()) {
            $this->db->update('lab_pcs', [
                'deployment_status' => $evaluation['status'],
                'deployment_reason' => $evaluation['reason'],
                'last_deploy_error' => $evaluation['eligible'] ? null : $evaluation['reason'],
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$pcId]);
        }
        return ['success' => true, 'pc_id' => $pcId] + $evaluation;
    }

    public function listDeploymentCandidates(bool $eligibleOnly = false): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM lab_pcs ORDER BY COALESCE(last_seen_at, last_heartbeat, updated_at) DESC");
        $out = [];
        foreach ($rows as $row) {
            $eval = $this->evaluateAutoDeployEligibility($row);
            if ($eligibleOnly && !$eval['eligible']) {
                continue;
            }
            $row['eligibility'] = $eval;
            $out[] = $row;
        }
        return $out;
    }

    public function queueDeploymentJob(int $pcId, string $triggerType = 'auto', ?int $createdBy = null, array $payload = []): array
    {
        $pc = $this->getPCById($pcId);
        if (!$pc) {
            return ['success' => false, 'error' => 'PC not found'];
        }
        $eval = $this->evaluateAutoDeployEligibility($pc);
        if (!$eval['eligible']) {
            if ($this->supportsAutoDeployColumns()) {
                $this->db->update('lab_pcs', [
                    'deployment_status' => 'excluded',
                    'deployment_reason' => $eval['reason'],
                    'last_deploy_error' => $eval['reason'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$pcId]);
            }
            return ['success' => false, 'error' => $eval['reason']];
        }
        $jobId = (int) $this->db->insert('pc_deployment_jobs', [
            'pc_id' => $pcId,
            'status' => 'queued',
            'trigger_type' => $triggerType,
            'created_by' => $createdBy,
            'request_payload' => !empty($payload) ? json_encode($payload) : null,
        ]);
        if ($this->supportsAutoDeployColumns()) {
            $this->db->update('lab_pcs', [
                'deployment_status' => 'pending',
                'deployment_reason' => 'Deployment queued',
                'last_deploy_error' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$pcId]);
        }
        return ['success' => true, 'job_id' => $jobId, 'pc_id' => $pcId];
    }

    public function getQueuedDeploymentJobs(int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT j.*, lp.hostname, lp.ip_address, lp.floor_id, lp.station_id
             FROM pc_deployment_jobs j
             JOIN lab_pcs lp ON lp.id = j.pc_id
             WHERE j.status = 'queued'
             ORDER BY j.created_at ASC
             LIMIT ?",
            [$limit]
        );
    }

    public function markDeploymentJobRunning(int $jobId): bool
    {
        $job = $this->db->fetch("SELECT * FROM pc_deployment_jobs WHERE id = ?", [$jobId]);
        if (!$job || $job['status'] !== 'queued') {
            return false;
        }
        $this->db->update('pc_deployment_jobs', [
            'status' => 'in_progress',
            'started_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$jobId]);
        if ($this->supportsAutoDeployColumns()) {
            $this->db->update('lab_pcs', [
                'deployment_status' => 'in_progress',
                'deployment_reason' => 'Deployment started',
                'last_deploy_attempt_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $job['pc_id']]);
        }
        return true;
    }

    public function completeDeploymentJob(int $jobId, bool $success, array $result = [], string $runnerLog = ''): bool
    {
        $job = $this->db->fetch("SELECT * FROM pc_deployment_jobs WHERE id = ?", [$jobId]);
        if (!$job) {
            return false;
        }
        $status = $success ? 'success' : 'failed';
        $this->db->update('pc_deployment_jobs', [
            'status' => $status,
            'result_payload' => json_encode($result),
            'runner_log' => $runnerLog !== '' ? $runnerLog : null,
            'finished_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$jobId]);
        if ($this->supportsAutoDeployColumns()) {
            $this->db->update('lab_pcs', [
                'deployment_status' => $success ? 'installed' : 'failed',
                'deployment_reason' => $success ? 'Deployment completed successfully' : 'Deployment failed',
                'last_deploy_error' => $success ? null : (string) ($result['error'] ?? 'Unknown deployment failure'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $job['pc_id']]);
        }
        return true;
    }

    public function getDeploymentStatusSummary(): array
    {
        if (!$this->supportsAutoDeployColumns()) {
            return ['pending' => 0, 'in_progress' => 0, 'installed' => 0, 'failed' => 0, 'excluded' => 0];
        }
        $rows = $this->db->fetchAll(
            "SELECT deployment_status, COUNT(*) AS cnt
             FROM lab_pcs
             GROUP BY deployment_status"
        );
        $summary = ['pending' => 0, 'in_progress' => 0, 'installed' => 0, 'failed' => 0, 'excluded' => 0];
        foreach ($rows as $row) {
            $k = (string) ($row['deployment_status'] ?? '');
            if ($k !== '' && array_key_exists($k, $summary)) {
                $summary[$k] = (int) $row['cnt'];
            }
        }
        return $summary;
    }

    public function queueUpdateJob(int $pcId, string $triggerType = 'manual', ?int $createdBy = null, array $payload = []): array
    {
        $pc = $this->getPCById($pcId);
        if (!$pc) {
            return ['success' => false, 'error' => 'PC not found'];
        }
        $jobPayload = array_merge(['mode' => 'update'], $payload);
        $jobId = (int) $this->db->insert('pc_deployment_jobs', [
            'pc_id' => $pcId,
            'status' => 'queued',
            'trigger_type' => $triggerType,
            'created_by' => $createdBy,
            'request_payload' => json_encode($jobPayload),
        ]);
        if ($this->supportsAutoDeployColumns()) {
            $this->db->update('lab_pcs', [
                'deployment_status' => 'pending',
                'deployment_reason' => 'Update queued',
                'last_deploy_error' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$pcId]);
        }
        return ['success' => true, 'job_id' => $jobId, 'pc_id' => $pcId];
    }

    public function getQueuedUpdateJobs(int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT j.*, lp.hostname, lp.ip_address, lp.floor_id, lp.station_id
             FROM pc_deployment_jobs j
             JOIN lab_pcs lp ON lp.id = j.pc_id
             WHERE j.status = 'queued'
               AND j.request_payload LIKE '%\"mode\":\"update\"%'
             ORDER BY j.created_at ASC
             LIMIT ?",
            [$limit]
        );
    }

    public function getRecentUpdateJobs(int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT j.id, j.pc_id, j.status, j.trigger_type, j.created_at, j.started_at, j.finished_at,
                    j.result_payload, j.runner_log,
                    lp.hostname, lp.ip_address
             FROM pc_deployment_jobs j
             JOIN lab_pcs lp ON lp.id = j.pc_id
             WHERE j.request_payload LIKE '%\"mode\":\"update\"%'
             ORDER BY j.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    public function getUpdateStatusSummary(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT j.status, COUNT(*) AS cnt
             FROM pc_deployment_jobs j
             WHERE j.request_payload LIKE '%\"mode\":\"update\"%'
             GROUP BY j.status"
        );
        $summary = ['queued' => 0, 'in_progress' => 0, 'success' => 0, 'failed' => 0, 'cancelled' => 0];
        foreach ($rows as $row) {
            $k = (string) ($row['status'] ?? '');
            if ($k !== '' && array_key_exists($k, $summary)) {
                $summary[$k] = (int) $row['cnt'];
            }
        }
        return $summary;
    }

    /**
     * Update PC status.
     */
    public function updatePCStatus(int $pcId, string $status): bool
    {
        $allowed = ['online', 'offline', 'locked', 'maintenance', 'idle'];
        if (!in_array($status, $allowed)) {
            return false;
        }
        $ok = $this->db->update('lab_pcs', ['status' => $status], 'id = ?', [$pcId]) > 0;
        $this->syncStationStatusFromPc($pcId);
        return $ok;
    }

    /**
     * Get all PCs for a floor.
     */
    public function getPCsByFloor(int $floorId): array
    {
        return $this->db->fetchAll(
            "SELECT lp.*, lf.name as floor_name
             FROM lab_pcs lp
             LEFT JOIN lab_floors lf ON lp.floor_id = lf.id
             WHERE lp.floor_id = ?
             ORDER BY lp.hostname ASC",
            [$floorId]
        );
    }

    /**
     * Get PCs that haven't sent heartbeat recently.
     */
    public function getStalePCs(int $timeoutSeconds = 300): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM lab_pcs
             WHERE status = 'online'
             AND (last_heartbeat IS NULL OR last_heartbeat < DATE_SUB(NOW(), INTERVAL ? SECOND))",
            [$timeoutSeconds]
        );
    }

    // =====================================================
    // PC Session Management
    // =====================================================

    /**
     * Start a new PC session for a student.
     */
    public function createSession(int $userId, int $pcId, ?int $stationId = null): array
    {
        // Check if user already has an active session
        $existing = $this->db->fetch(
            "SELECT * FROM pc_sessions WHERE user_id = ? AND status = 'active'",
            [$userId]
        );

        if ($existing) {
            return [
                'success' => false,
                'error' => 'User already has an active session',
                'session_id' => $existing['id'],
            ];
        }

        // Check if PC already has an active session
        $pcExisting = $this->db->fetch(
            "SELECT * FROM pc_sessions WHERE pc_id = ? AND status = 'active'",
            [$pcId]
        );

        if ($pcExisting) {
            return [
                'success' => false,
                'error' => 'PC already has an active session',
            ];
        }

        $sessionId = $this->db->insert('pc_sessions', [
            'user_id' => $userId,
            'pc_id' => $pcId,
            'station_id' => $stationId,
            'checkin_time' => date('Y-m-d H:i:s'),
            'status' => 'active',
        ]);

        // Update PC status to idle (occupied but not locked)
        $this->updatePCStatus($pcId, 'idle');

        return [
            'success' => true,
            'session_id' => $sessionId,
            'checkin_time' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * End a PC session.
     */
    public function endSession(int $sessionId, string $reason = 'normal'): bool
    {
        $result = $this->db->update('pc_sessions', [
            'checkout_time' => date('Y-m-d H:i:s'),
            'status' => 'completed',
            'checkout_reason' => $reason,
        ], 'id = ?', [$sessionId]);

        // Get session to update PC status
        $session = $this->db->fetch("SELECT pc_id FROM pc_sessions WHERE id = ?", [$sessionId]);
        if ($session) {
            $this->updatePCStatus($session['pc_id'], 'online');
        }

        return $result > 0;
    }

    /**
     * End all active sessions for a user.
     */
    public function endUserSessions(int $userId, string $reason = 'normal'): int
    {
        $sessions = $this->db->fetchAll(
            "SELECT id, pc_id FROM pc_sessions WHERE user_id = ? AND status = 'active'",
            [$userId]
        );

        $count = 0;
        foreach ($sessions as $session) {
            $this->endSession($session['id'], $reason);
            $count++;
        }

        return $count;
    }

    /**
     * Get active session for a PC.
     */
    public function getActiveSession(int $pcId): ?array
    {
        return $this->db->fetch(
            "SELECT ps.*, u.first_name, u.last_name, u.lrn, u.role,
                    u.grade_level, u.section, u.course_id
             FROM pc_sessions ps
             JOIN users u ON ps.user_id = u.id
             WHERE ps.pc_id = ? AND ps.status = 'active'",
            [$pcId]
        );
    }

    /**
     * Get active session for a user.
     */
    public function getUserActiveSession(int $userId): ?array
    {
        return $this->db->fetch(
            "SELECT ps.*, lp.hostname
             FROM pc_sessions ps
             JOIN lab_pcs lp ON ps.pc_id = lp.id
             WHERE ps.user_id = ? AND ps.status = 'active'",
            [$userId]
        );
    }

    /**
     * Validate if a user can check in at a PC.
     */
    public function validateCheckIn(int $userId, int $pcId): array
    {
        $user = $this->db->fetch("SELECT * FROM users WHERE id = ? AND is_active = 1", [$userId]);
        if (!$user) {
            return ['valid' => false, 'error' => 'User not found or inactive'];
        }

        $pc = $this->getPCById($pcId);
        if (!$pc) {
            return ['valid' => false, 'error' => 'PC not found'];
        }

        if ($pc['status'] === 'maintenance' || $pc['status'] === 'locked') {
            return ['valid' => false, 'error' => 'PC is unavailable'];
        }

        $existingSession = $this->getUserActiveSession($userId);
        if ($existingSession) {
            return [
                'valid' => false,
                'error' => 'User already has an active session',
                'existing_pc' => $existingSession['hostname'],
            ];
        }

        return ['valid' => true, 'user' => $user, 'pc' => $pc];
    }

    // =====================================================
    // Remote Commands
    // =====================================================

    /**
     * Queue a remote command for a PC.
     */
    public function queueCommand(int $pcId, int $issuedBy, string $commandType, ?array $params = null, int $ttlSeconds = 300): array
    {
        $allowed = ['lock', 'unlock', 'shutdown', 'restart', 'message', 'screenshot'];
        if (!in_array($commandType, $allowed)) {
            return ['success' => false, 'error' => 'Invalid command type'];
        }

        $commandId = $this->db->insert('remote_commands', [
            'pc_id' => $pcId,
            'issued_by' => $issuedBy,
            'command_type' => $commandType,
            'params' => $params ? json_encode($params) : null,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
        ]);

        return [
            'success' => true,
            'command_id' => $commandId,
            'message' => 'Command queued',
        ];
    }

    /**
     * Get pending commands for a PC.
     */
    public function getPendingCommands(int $pcId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM remote_commands
             WHERE pc_id = ?
             AND status = 'pending'
             AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY created_at ASC",
            [$pcId]
        );
    }

    public function getPendingCommandsAfterCursor(int $pcId, int $afterCursor = 0): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM remote_commands
             WHERE pc_id = ?
               AND id > ?
               AND status = 'pending'
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY id ASC",
            [$pcId, $afterCursor]
        );
    }

    /**
     * Mark a command as executed.
     */
    public function executeCommand(int $commandId, ?string $result = null): bool
    {
        return $this->db->update('remote_commands', [
            'status' => 'executed',
            'result' => $result,
            'executed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$commandId]) > 0;
    }

    /**
     * Mark a command as failed.
     */
    public function failCommand(int $commandId, string $error): bool
    {
        return $this->db->update('remote_commands', [
            'status' => 'failed',
            'result' => $error,
            'executed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$commandId]) > 0;
    }

    // =====================================================
    // Drive Mappings
    // =====================================================

    /**
     * Get drive mappings for a role.
     */
    public function getDriveMappings(string $role): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM drive_mappings
             WHERE role = ? AND is_active = 1
             ORDER BY sort_order ASC",
            [$role]
        );
    }

    /**
     * Get all active drive mappings.
     */
    public function getAllDriveMappings(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM drive_mappings WHERE is_active = 1 ORDER BY role, sort_order ASC"
        );
    }

    // =====================================================
    // Folder Access Rules
    // =====================================================

    /**
     * Get folder access rules for a floor and role.
     */
    public function getFolderRules(?int $floorId = null, string $role = ''): array
    {
        $sql = "SELECT * FROM folder_access_rules WHERE is_active = 1";
        $params = [];

        if ($floorId !== null) {
            $sql .= " AND (floor_id = ? OR floor_id IS NULL)";
            $params[] = $floorId;
        }

        if (!empty($role)) {
            $sql .= " AND (role = ? OR role = 'admin')";
            $params[] = $role;
        }

        $sql .= " ORDER BY floor_id IS NULL, role ASC";

        return $this->db->fetchAll($sql, $params);
    }

    // =====================================================
    // Statistics & Reporting
    // =====================================================

    /**
     * Get lab PC statistics.
     */
    public function getStats(?int $floorId = null): array
    {
        $sql = "SELECT id, status, last_heartbeat FROM lab_pcs";
        $params = [];
        if ($floorId !== null) {
            $sql .= " WHERE floor_id = ?";
            $params[] = $floorId;
        }
        $pcs = $this->db->fetchAll($sql, $params);
        $total = count($pcs);
        $online = 0;
        $idle = 0;
        $locked = 0;
        $offline = 0;
        $maintenance = 0;
        foreach ($pcs as $pc) {
            $effective = $this->resolveEffectivePcStatus($pc);
            if ($effective === 'online') {
                $online++;
            } elseif ($effective === 'idle') {
                $idle++;
            } elseif ($effective === 'locked') {
                $locked++;
            } elseif ($effective === 'maintenance') {
                $maintenance++;
            } else {
                $offline++;
            }
        }
        $activeSessions = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM pc_sessions ps JOIN lab_pcs lp ON ps.pc_id = lp.id WHERE ps.status = 'active' " . ($floorId ? "AND lp.floor_id = $floorId" : "")
        );

        return [
            'total' => $total,
            'online' => $online,
            'idle' => $idle,
            'locked' => $locked,
            'offline' => $offline,
            'maintenance' => $maintenance,
            'active_sessions' => $activeSessions,
        ];
    }

    /**
     * Get all active PC sessions with user details.
     */
    public function getActiveSessions(?int $floorId = null): array
    {
        $sql = "SELECT ps.*, u.first_name, u.last_name, u.lrn, u.grade_level, u.section,
                       lp.hostname, lp.ip_address, lp.floor_id
                FROM pc_sessions ps
                JOIN users u ON ps.user_id = u.id
                JOIN lab_pcs lp ON ps.pc_id = lp.id
                WHERE ps.status = 'active'";
        $params = [];

        if ($floorId !== null) {
            $sql .= " AND lp.floor_id = ?";
            $params[] = $floorId;
        }

        $sql .= " ORDER BY ps.checkin_time ASC";

        return $this->db->fetchAll($sql, $params);
    }
}