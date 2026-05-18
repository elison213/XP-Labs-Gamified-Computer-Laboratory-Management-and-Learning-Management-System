<?php
/**
 * XPLabs - Lab Service
 * Manages lab floors, stations, and real-time status.
 */

namespace XPLabs\Services;

use XPLabs\Lib\Database;

class LabService
{
    private Database $db;
    private int $heartbeatOfflineThresholdSeconds = 300;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all labs.
     */
    public function getLabs(): array
    {
        return $this->db->fetchAll("SELECT * FROM labs WHERE is_active = 1 ORDER BY name ASC");
    }

    /**
     * Get a single lab.
     */
    public function getLab(int $labId): ?array
    {
        return $this->db->fetch("SELECT * FROM labs WHERE id = ?", [$labId]);
    }

    /**
     * Create a new lab.
     */
    public function createLab(array $data): int
    {
        return $this->db->insert('labs', [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'building' => $data['building'] ?? null,
            'floor_number' => $data['floor_number'] ?? 1,
            'grid_cols' => $data['grid_cols'] ?? 6,
            'grid_rows' => $data['grid_rows'] ?? 5,
            'layout_config' => json_encode($data['layout_config'] ?? []),
            'is_active' => 1,
        ]);
    }

    /**
     * Update a lab.
     */
    public function updateLab(int $labId, array $data): bool
    {
        $allowed = ['name', 'description', 'building', 'floor_number', 'grid_cols', 'grid_rows', 'layout_config', 'is_active'];
        $update = array_intersect_key($data, array_flip($allowed));

        if (isset($update['layout_config']) && is_array($update['layout_config'])) {
            $update['layout_config'] = json_encode($update['layout_config']);
        }

        return $this->db->update('labs', $update, 'id = ?', [$labId]) > 0;
    }

    /**
     * Delete a lab and all floors/stations belonging to it.
     */
    public function deleteLab(int $labId): bool
    {
        $this->db->beginTransaction();
        try {
            $floorRows = $this->db->fetchAll('SELECT id FROM lab_floors WHERE lab_id = ?', [$labId]);
            foreach ($floorRows as $fr) {
                $floorId = (int) $fr['id'];
                $stationRows = $this->db->fetchAll('SELECT id FROM lab_stations WHERE floor_id = ?', [$floorId]);
                foreach ($stationRows as $sr) {
                    $this->deleteStation((int) $sr['id']);
                }
                $this->db->delete('lab_floors', 'id = ?', [$floorId]);
            }
            $deleted = $this->db->delete('labs', 'id = ?', [$labId]) > 0;
            $this->db->commit();
            return $deleted;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get all lab floors.
     */
    public function getFloors(?int $labId = null): array
    {
        $sql = "SELECT * FROM lab_floors WHERE is_active = 1";
        $params = [];
        if ($labId) {
            $sql .= " AND lab_id = ?";
            $params[] = $labId;
        }
        $sql .= " ORDER BY name ASC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get a single lab floor.
     */
    public function getFloor(int $floorId): ?array
    {
        return $this->db->fetch("SELECT * FROM lab_floors WHERE id = ?", [$floorId]);
    }

    /**
     * Create a new lab floor.
     */
    public function createFloor(array $data): int
    {
        $payload = [
            'name' => $data['name'],
            'building' => $data['building'] ?? null,
            'floor_number' => $data['floor_number'] ?? 1,
            'grid_cols' => $data['grid_cols'] ?? 6,
            'grid_rows' => $data['grid_rows'] ?? 5,
            'layout_config' => json_encode($data['layout_config'] ?? []),
            'is_active' => 1,
        ];

        // Optional: if lab_floors.lab_id exists (migration 033), persist it.
        if (isset($data['lab_id'])) {
            $hasLabId = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'lab_floors' AND column_name = 'lab_id'"
            );
            if ($hasLabId > 0) {
                $payload['lab_id'] = (int) $data['lab_id'];
            }
        }

        return $this->db->insert('lab_floors', $payload);
    }

    /**
     * Update a lab floor.
     */
    public function updateFloor(int $floorId, array $data): bool
    {
        $allowed = ['name', 'building', 'floor_number', 'grid_cols', 'grid_rows', 'layout_config', 'is_active'];
        $update = array_intersect_key($data, array_flip($allowed));

        if (isset($update['layout_config']) && is_array($update['layout_config'])) {
            $update['layout_config'] = json_encode($update['layout_config']);
        }

        return $this->db->update('lab_floors', $update, 'id = ?', [$floorId]) > 0;
    }

    /**
     * Get all stations for a floor.
     */
    public function getStations(?int $floorId = null): array
    {
        $sql = "SELECT ls.*, lf.name as floor_name,
                       u.first_name, u.last_name,
                       sa.assigned_at as checkin_time,
                       sa.task,
                       lp.id as pc_id,
                       lp.hostname as pc_hostname,
                       lp.ip_address as pc_ip_address,
                       lp.status as pc_status,
                       lp.last_heartbeat as pc_last_heartbeat
                FROM lab_stations ls
                LEFT JOIN lab_floors lf ON ls.floor_id = lf.id
                LEFT JOIN station_assignments sa ON ls.id = sa.station_id
                LEFT JOIN users u ON sa.user_id = u.id
                LEFT JOIN lab_pcs lp ON lp.station_id = ls.id
                WHERE 1=1";
        $params = [];

        if ($floorId) {
            $sql .= " AND ls.floor_id = ?";
            $params[] = $floorId;
        }

        $sql .= " ORDER BY COALESCE(ls.sort_order, 0) ASC, ls.station_code ASC";

        $rows = $this->db->fetchAll($sql, $params);
        return array_map(function (array $row): array {
            $row['manual_status'] = $row['status'] ?? 'offline';
            $row['status'] = $this->resolveEffectiveStationStatus($row);
            if (empty($row['hostname']) && !empty($row['pc_hostname'])) {
                $row['hostname'] = $row['pc_hostname'];
            }
            if (empty($row['ip_address']) && !empty($row['pc_ip_address'])) {
                $row['ip_address'] = $row['pc_ip_address'];
            }
            return $row;
        }, $rows);
    }

    /**
     * Get a single station.
     */
    public function getStation(int $stationId): ?array
    {
        return $this->db->fetch(
            "SELECT ls.*, lf.name as floor_name
             FROM lab_stations ls
             LEFT JOIN lab_floors lf ON ls.floor_id = lf.id
             WHERE ls.id = ?",
            [$stationId]
        );
    }

    /**
     * Create a new station.
     */
    public function createStation(array $data): int
    {
        return $this->db->insert('lab_stations', [
            'floor_id' => $data['floor_id'],
            'station_code' => $data['station_code'],
            'row_label' => $data['row_label'] ?? 'A',
            'col_number' => $data['col_number'] ?? 1,
            'status' => $data['status'] ?? 'offline',
            'hostname' => $data['hostname'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'mac_address' => $data['mac_address'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    /**
     * Update a station.
     */
    public function updateStation(int $stationId, array $data): bool
    {
        $allowed = ['station_code', 'row_label', 'col_number', 'status', 'hostname', 'ip_address', 'mac_address', 'sort_order'];
        $update = array_intersect_key($data, array_flip($allowed));
        if (empty($update)) {
            return false;
        }
        $ok = $this->db->update('lab_stations', $update, 'id = ?', [$stationId]) > 0;
        if (!$ok) {
            return false;
        }
        $pcSync = [];
        if (array_key_exists('hostname', $update)) {
            $pcSync['hostname'] = $update['hostname'] ?: null;
        }
        if (array_key_exists('ip_address', $update)) {
            $pcSync['ip_address'] = $update['ip_address'] ?: null;
        }
        if (array_key_exists('mac_address', $update)) {
            $pcSync['mac_address'] = $update['mac_address'] ?: null;
        }
        if (array_key_exists('status', $update)) {
            $mapped = strtolower((string) $update['status']);
            if ($mapped === 'active') {
                $mapped = 'online';
            }
            if (!in_array($mapped, ['online', 'idle', 'locked', 'offline', 'maintenance'], true)) {
                $mapped = 'offline';
            }
            $pcSync['status'] = $mapped;
        }
        if (!empty($pcSync)) {
            $pcSync['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('lab_pcs', $pcSync, 'station_id = ?', [$stationId]);
        }
        return true;
    }

    /**
     * Delete a station.
     */
    public function deleteStation(int $stationId): bool
    {
        $this->db->beginTransaction();
        try {
            $this->db->query(
                "UPDATE lab_pcs SET station_id = NULL, updated_at = NOW() WHERE station_id = ?",
                [$stationId]
            );
            $deleted = $this->db->delete('lab_stations', 'id = ?', [$stationId]) > 0;
            if (!$deleted) {
                $this->db->rollback();
                return false;
            }
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Lock a station (teacher control).
     */
    public function lockStation(int $stationId): bool
    {
        return $this->db->update('lab_stations', ['status' => 'locked'], 'id = ?', [$stationId]) > 0;
    }

    /**
     * Unlock a station.
     */
    public function unlockStation(int $stationId): bool
    {
        return $this->db->update('lab_stations', ['status' => 'offline'], 'id = ?', [$stationId]) > 0;
    }

    /**
     * Get floor layout (for visual editor).
     */
    public function getFloorLayout(int $floorId): array
    {
        $floor = $this->getFloor($floorId);
        if (!$floor) {
            return [];
        }

        $stations = $this->getStations($floorId);
        $layoutConfig = json_decode($floor['layout_config'], true) ?? [];

        return [
            'floor' => $floor,
            'stations' => $stations,
            'grid' => [
                'cols' => $floor['grid_cols'],
                'rows' => $floor['grid_rows'],
            ],
            'layout_config' => $layoutConfig,
        ];
    }

    /**
     * Save floor layout (from visual editor).
     */
    public function saveFloorLayout(int $floorId, array $layoutData): bool
    {
        $this->db->beginTransaction();
        try {
            $gridCols = (int) ($layoutData['grid']['cols'] ?? 6);
            $gridRows = (int) ($layoutData['grid']['rows'] ?? 5);
            
            // Update floor grid config
            $result = $this->db->update('lab_floors', [
                'grid_cols' => $gridCols,
                'grid_rows' => $gridRows,
                'layout_config' => json_encode($layoutData['config'] ?? []),
            ], 'id = ?', [$floorId]);
            // Intentionally no verbose logging here; layout saves can be frequent.

            // Update stations - first clear all positions to avoid unique constraint conflicts
            if (!empty($layoutData['stations'])) {
                // Step 1: Temporarily set all stations to NULL position
                $this->db->query(
                    "UPDATE lab_stations SET row_label = NULL, col_number = NULL WHERE floor_id = ?",
                    [$floorId]
                );
                
                // Step 2: Now set the new positions
                foreach ($layoutData['stations'] as $stationData) {
                    if (!empty($stationData['id'])) {
                        $this->updateStation((int) $stationData['id'], [
                            'row_label' => $stationData['row_label'] ?? 'A',
                            'col_number' => (int) ($stationData['col_number'] ?? 1),
                        ]);
                    }
                }
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("saveFloorLayout error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get station statistics.
     */
    public function getStats(?int $floorId = null): array
    {
        $stations = $this->getStations($floorId);
        $total = count($stations);
        $active = 0;
        $idle = 0;
        $locked = 0;
        $offline = 0;
        $maintenance = 0;
        foreach ($stations as $station) {
            $status = (string) ($station['status'] ?? 'offline');
            if ($status === 'active') {
                $active++;
            } elseif ($status === 'idle') {
                $idle++;
            } elseif ($status === 'locked') {
                $locked++;
            } elseif ($status === 'maintenance') {
                $maintenance++;
            } else {
                $offline++;
            }
        }

        return [
            'total' => $total,
            'active' => $active,
            'idle' => $idle,
            'locked' => $locked,
            'offline' => $offline,
            'maintenance' => $maintenance,
        ];
    }

    private function resolveEffectiveStationStatus(array $station): string
    {
        if (!empty($station['is_maintenance']) || ($station['status'] ?? '') === 'maintenance') {
            return 'maintenance';
        }

        $pcStatus = strtolower(trim((string) ($station['pc_status'] ?? '')));
        $lastHeartbeat = $station['pc_last_heartbeat'] ?? null;
        if ($pcStatus !== '') {
            if ($lastHeartbeat) {
                $heartbeatTs = strtotime((string) $lastHeartbeat);
                if ($heartbeatTs !== false && (time() - $heartbeatTs) > $this->heartbeatOfflineThresholdSeconds) {
                    return 'offline';
                }
            }
            if ($pcStatus === 'locked') {
                return 'locked';
            }
            if ($pcStatus === 'online') {
                return !empty($station['checkin_time']) ? 'active' : 'idle';
            }
            if (in_array($pcStatus, ['idle', 'maintenance', 'offline'], true)) {
                return $pcStatus;
            }
        }

        $fallback = strtolower(trim((string) ($station['status'] ?? 'offline')));
        if (in_array($fallback, ['active', 'idle', 'locked', 'offline', 'maintenance'], true)) {
            return $fallback;
        }
        return 'offline';
    }

    /**
     * Bulk update station statuses.
     */
    public function bulkUpdateStatus(array $stationIds, string $status): int
    {
        if (empty($stationIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($stationIds), '?'));
        $updated = $this->db->query(
            "UPDATE lab_stations SET status = ? WHERE id IN ($placeholders)",
            array_merge([$status], $stationIds)
        )->rowCount();
        $pcStatus = strtolower($status);
        if ($pcStatus === 'active') {
            $pcStatus = 'online';
        }
        if (!in_array($pcStatus, ['online', 'idle', 'locked', 'offline', 'maintenance'], true)) {
            $pcStatus = 'offline';
        }
        $this->db->query(
            "UPDATE lab_pcs SET status = ?, updated_at = NOW() WHERE station_id IN ($placeholders)",
            array_merge([$pcStatus], $stationIds)
        );
        return $updated;
    }
}