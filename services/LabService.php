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
     * Delete a lab.
     */
    public function deleteLab(int $labId): bool
    {
        return $this->db->delete('labs', 'id = ?', [$labId]) > 0;
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
        return $this->db->insert('lab_floors', [
            'name' => $data['name'],
            'building' => $data['building'] ?? null,
            'floor_number' => $data['floor_number'] ?? 1,
            'grid_cols' => $data['grid_cols'] ?? 6,
            'grid_rows' => $data['grid_rows'] ?? 5,
            'layout_config' => json_encode($data['layout_config'] ?? []),
            'is_active' => 1,
        ]);
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
                       sa.task
                FROM lab_stations ls
                LEFT JOIN lab_floors lf ON ls.floor_id = lf.id
                LEFT JOIN station_assignments sa ON ls.id = sa.station_id
                LEFT JOIN users u ON sa.user_id = u.id
                WHERE 1=1";
        $params = [];

        if ($floorId) {
            $sql .= " AND ls.floor_id = ?";
            $params[] = $floorId;
        }

        $sql .= " ORDER BY COALESCE(ls.sort_order, 0) ASC, ls.station_code ASC";

        return $this->db->fetchAll($sql, $params);
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

        return $this->db->update('lab_stations', $update, 'id = ?', [$stationId]) > 0;
    }

    /**
     * Delete a station.
     */
    public function deleteStation(int $stationId): bool
    {
        return $this->db->delete('lab_stations', 'id = ?', [$stationId]) > 0;
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
            // Update floor grid config
            $this->db->update('lab_floors', [
                'grid_cols' => $layoutData['grid']['cols'] ?? 6,
                'grid_rows' => $layoutData['grid']['rows'] ?? 5,
                'layout_config' => json_encode($layoutData['config'] ?? []),
            ], 'id = ?', [$floorId]);

            // Update stations
            if (!empty($layoutData['stations'])) {
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
        $where = 'WHERE 1=1';
        $params = [];

        if ($floorId) {
            $where .= ' AND floor_id = ?';
            $params[] = $floorId;
        }

        $total = (int) $this->db->fetchOne("SELECT COUNT(*) FROM lab_stations $where", $params);
        $active = (int) $this->db->fetchOne("SELECT COUNT(*) FROM lab_stations $where AND status = 'active'", $params);
        $idle = (int) $this->db->fetchOne("SELECT COUNT(*) FROM lab_stations $where AND status = 'idle'", $params);
        $offline = (int) $this->db->fetchOne("SELECT COUNT(*) FROM lab_stations $where AND status = 'offline'", $params);
        $maintenance = (int) $this->db->fetchOne("SELECT COUNT(*) FROM lab_stations $where AND status = 'maintenance'", $params);

        return [
            'total' => $total,
            'active' => $active,
            'idle' => $idle,
            'offline' => $offline,
            'maintenance' => $maintenance,
        ];
    }

    /**
     * Bulk update station statuses.
     */
    public function bulkUpdateStatus(array $stationIds, string $status): int
    {
        $placeholders = implode(',', array_fill(0, count($stationIds), '?'));
        return $this->db->query(
            "UPDATE lab_stations SET status = ? WHERE id IN ($placeholders)",
            array_merge([$status], $stationIds)
        )->rowCount();
    }
}