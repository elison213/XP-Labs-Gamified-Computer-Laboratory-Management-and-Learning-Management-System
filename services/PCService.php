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

    public function __construct()
    {
        $this->db = Database::getInstance();
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

        if (empty($hostname)) {
            return ['success' => false, 'error' => 'Hostname is required'];
        }

        // Check if PC already exists
        $existing = $this->db->fetch("SELECT * FROM lab_pcs WHERE hostname = ?", [$hostname]);

        if ($existing) {
            // Update existing PC
            $this->db->update('lab_pcs', [
                'ip_address' => $ipAddress,
                'mac_address' => $macAddress,
                'floor_id' => $floorId,
                'station_id' => $stationId,
                'status' => 'online',
                'last_heartbeat' => date('Y-m-d H:i:s'),
            ], 'hostname = ?', [$hostname]);

            return [
                'success' => true,
                'pc_id' => $existing['id'],
                'machine_key' => $existing['machine_key'],
                'message' => 'PC already registered',
            ];
        }

        // Generate machine key
        $machineKey = bin2hex(random_bytes(32));

        $pcId = $this->db->insert('lab_pcs', [
            'hostname' => $hostname,
            'ip_address' => $ipAddress,
            'mac_address' => $macAddress,
            'floor_id' => $floorId,
            'station_id' => $stationId,
            'machine_key' => $machineKey,
            'status' => 'online',
            'last_heartbeat' => date('Y-m-d H:i:s'),
        ]);

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
        return $this->db->update('lab_pcs', [
            'last_heartbeat' => date('Y-m-d H:i:s'),
            'status' => 'online',
        ], 'id = ?', [$pcId]) > 0;
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
        return $this->db->update('lab_pcs', ['status' => $status], 'id = ?', [$pcId]) > 0;
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
        $where = 'WHERE 1=1';
        $params = [];

        if ($floorId !== null) {
            $where .= ' AND floor_id = ?';
            $params[] = $floorId;
        }

        $total = (int) $this->db->fetchOne("SELECT COUNT(*) FROM lab_pcs $where", $params);
        $online = (int) $this->db->fetchOne("SELECT COUNT(*) FROM lab_pcs $where AND status = 'online'", $params);
        $idle = (int) $this->db->fetchOne("SELECT COUNT(*) FROM lab_pcs $where AND status = 'idle'", $params);
        $locked = (int) $this->db->fetchOne("SELECT COUNT(*) FROM lab_pcs $where AND status = 'locked'", $params);
        $offline = (int) $this->db->fetchOne("SELECT COUNT(*) FROM lab_pcs $where AND status = 'offline'", $params);
        $maintenance = (int) $this->db->fetchOne("SELECT COUNT(*) FROM lab_pcs $where AND status = 'maintenance'", $params);
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