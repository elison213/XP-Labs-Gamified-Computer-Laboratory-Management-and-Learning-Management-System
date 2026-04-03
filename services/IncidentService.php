<?php
namespace XPLabs\Services;

use XPLabs\Lib\Database;

class IncidentService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get all incidents with filtering options
     */
    public function getIncidents($filters = []) {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['severity'])) {
            $where[] = 'i.severity = ?';
            $params[] = $filters['severity'];
        }
        if (!empty($filters['type'])) {
            $where[] = 'i.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['lab_id'])) {
            $where[] = 'i.lab_id = ?';
            $params[] = $filters['lab_id'];
        }
        if (!empty($filters['limit'])) {
            $limit = (int) $filters['limit'];
        } else {
            $limit = 100;
        }

        $whereClause = implode(' AND ', $where);

        return $this->db->fetchAll(
            "SELECT i.*, 
                    u1.first_name as reporter_first, u1.last_name as reporter_last,
                    u2.first_name as assignee_first, u2.last_name as assignee_last,
                    l.name as lab_name, lf.name as floor_name
             FROM incidents i
             JOIN users u1 ON i.reported_by = u1.id
             LEFT JOIN users u2 ON i.assigned_to = u2.id
             LEFT JOIN labs l ON i.lab_id = l.id
             LEFT JOIN lab_floors lf ON i.floor_id = lf.id
             WHERE $whereClause
             ORDER BY i.created_at DESC
             LIMIT $limit",
            $params
        );
    }

    /**
     * Get a single incident by ID
     */
    public function getIncident($id) {
        $id = (int) $id;
        return $this->db->fetch(
            "SELECT i.*, 
                    u1.first_name as reporter_first, u1.last_name as reporter_last,
                    u2.first_name as assignee_first, u2.last_name as assignee_last,
                    l.name as lab_name, lf.name as floor_name
             FROM incidents i
             JOIN users u1 ON i.reported_by = u1.id
             LEFT JOIN users u2 ON i.assigned_to = u2.id
             LEFT JOIN labs l ON i.lab_id = l.id
             LEFT JOIN lab_floors lf ON i.floor_id = lf.id
             WHERE i.id = ?",
            [$id]
        );
    }

    /**
     * Create a new incident
     */
    public function createIncident($data, $reportedBy) {
        $incidentData = [
            'type' => $data['type'] ?? 'general',
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'severity' => $data['severity'] ?? 'medium',
            'status' => 'reported',
            'location' => $data['location'] ?? null,
            'lab_id' => !empty($data['lab_id']) ? (int)$data['lab_id'] : null,
            'floor_id' => !empty($data['floor_id']) ? (int)$data['floor_id'] : null,
            'reported_by' => (int)$reportedBy,
        ];

        $incidentId = $this->db->insert('incidents', $incidentData);

        if ($incidentId) {
            $this->logIncidentAction($incidentId, 'created', null, null, 'Incident created', $reportedBy);
        }

        return $incidentId;
    }

    /**
     * Update an incident
     */
    public function updateIncident($id, $data, $performedBy) {
        $incident = $this->getIncident($id);
        if (!$incident) {
            return false;
        }

        $updates = [];
        foreach (['status', 'severity', 'assigned_to', 'resolution_notes', 'lab_id', 'floor_id'] as $field) {
            if (isset($data[$field])) {
                $oldValue = $incident[$field];
                $newValue = $data[$field];
                if ($oldValue != $newValue) {
                    $this->logIncidentAction($id, $field . '_changed', $oldValue, $newValue, "Changed $field", $performedBy);
                }
                $updates[$field] = $newValue;
            }
        }

        if (isset($data['title'])) {
            $this->logIncidentAction($id, 'title_changed', $incident['title'], $data['title'], 'Updated title', $performedBy);
            $updates['title'] = $data['title'];
        }
        if (isset($data['description'])) {
            $this->logIncidentAction($id, 'description_changed', $incident['description'], $data['description'], 'Updated description', $performedBy);
            $updates['description'] = $data['description'];
        }

        // Auto-set resolved_at if status changed to resolved
        if (isset($data['status']) && $data['status'] === 'resolved' && $incident['status'] !== 'resolved') {
            $updates['resolved_at'] = date('Y-m-d H:i:s');
        }

        if (empty($updates)) {
            return true;
        }

        return $this->db->update('incidents', $updates, 'id = ?', [(int)$id]);
    }

    /**
     * Delete an incident
     */
    public function deleteIncident($id) {
        return $this->db->delete('incidents', 'id = ?', [(int)$id]);
    }

    /**
     * Get incident statistics
     */
    public function getStats($labId = null) {
        $where = '1=1';
        $params = [];
        
        if ($labId) {
            $where = 'lab_id = ?';
            $params[] = (int)$labId;
        }

        return $this->db->fetch(
            "SELECT 
                    COUNT(*) as total,
                    status, severity,
                    SUM(CASE WHEN status = 'reported' THEN 1 ELSE 0 END) as reported_count,
                    SUM(CASE WHEN status = 'investigating' THEN 1 ELSE 0 END) as investigating_count,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
                    SUM(CASE WHEN status = 'dismissed' THEN 1 ELSE 0 END) as dismissed_count,
                    SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count,
                    SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high_count
             FROM incidents
             WHERE $where
             GROUP BY status, severity",
            $params
        );
    }

    /**
     * Get incident types
     */
    public static function getIncidentTypes() {
        return [
            'hardware' => 'Hardware Issue',
            'software' => 'Software Issue',
            'network' => 'Network Issue',
            'safety' => 'Safety Concern',
            'misconduct' => 'Student Misconduct',
            'maintenance' => 'Maintenance Required',
            'general' => 'General',
        ];
    }

    /**
     * Log an incident action
     */
    private function logIncidentAction($incidentId, $action, $oldValue, $newValue, $notes, $performedBy) {
        $this->db->insert('incident_logs', [
            'incident_id' => (int)$incidentId,
            'action' => $action,
            'old_value' => is_string($oldValue) ? substr($oldValue, 0, 255) : $oldValue,
            'new_value' => is_string($newValue) ? substr($newValue, 0, 255) : $newValue,
            'notes' => $notes,
            'performed_by' => (int)$performedBy,
        ]);
    }

    /**
     * Get incident logs
     */
    public function getIncidentLogs($incidentId) {
        return $this->db->fetchAll(
            "SELECT il.*, u.first_name, u.last_name
             FROM incident_logs il
             JOIN users u ON il.performed_by = u.id
             WHERE il.incident_id = ?
             ORDER BY il.created_at DESC",
            [(int)$incidentId]
        );
    }
}