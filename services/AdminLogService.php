<?php

namespace XPLabs\Services;

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

class AdminLogService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tableExists(): bool
    {
        return $this->db->tableExists('admin_logs');
    }

    /**
     * @param array<string, mixed>|string|null $details
     */
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        $details = null,
        ?int $userId = null
    ): void {
        if (!$this->tableExists()) {
            return;
        }

        if ($userId === null) {
            try {
                $userId = Auth::id();
            } catch (\Throwable $e) {
                $userId = null;
            }
        }

        $encoded = null;
        if ($details !== null) {
            if (is_array($details)) {
                $encoded = json_encode($details, JSON_UNESCAPED_UNICODE);
            } else {
                $encoded = json_encode(['message' => (string) $details], JSON_UNESCAPED_UNICODE);
            }
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip) && strlen($ip) > 15) {
            $ip = substr($ip, 0, 15);
        }

        try {
            $this->db->insert('admin_logs', [
                'user_id' => $userId > 0 ? $userId : null,
                'action' => substr($action, 0, 100),
                'entity_type' => $entityType !== null && $entityType !== '' ? substr($entityType, 0, 50) : null,
                'entity_id' => $entityId > 0 ? $entityId : null,
                'details' => $encoded,
                'ip_address' => $ip,
            ]);
        } catch (\Throwable $e) {
            error_log('AdminLogService::log failed: ' . $e->getMessage());
        }
    }

    public function formatTarget(?string $entityType, $entityId): string
    {
        if ($entityType === null || $entityType === '') {
            return '—';
        }
        $id = (int) $entityId;
        return $id > 0 ? ($entityType . ' #' . $id) : $entityType;
    }

    public function formatDetailsForDisplay($details): string
    {
        if ($details === null || $details === '') {
            return '';
        }
        if (is_string($details)) {
            $decoded = json_decode($details, true);
            if (is_array($decoded)) {
                return $this->formatDetailsArray($decoded);
            }
            return $details;
        }
        if (is_array($details)) {
            return $this->formatDetailsArray($details);
        }
        return (string) $details;
    }

    private function formatDetailsArray(array $data): string
    {
        $parts = [];
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_scalar($value)) {
                $parts[] = $key . ': ' . $value;
            } else {
                $parts[] = $key . ': ' . json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }
        return implode(' · ', $parts);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = [], int $limit = 500): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $limit = max(1, min(5000, $limit));
        $where = ['1=1'];
        $params = [];

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $userId = (int) ($filters['user_id'] ?? 0);

        if ($dateFrom !== '') {
            $where[] = 'al.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $where[] = 'al.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        if ($userId > 0) {
            $where[] = 'al.user_id = ?';
            $params[] = $userId;
        }
        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(al.action LIKE ? OR al.entity_type LIKE ? OR al.details LIKE ?
                OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.lrn LIKE ? OR u.email LIKE ?)';
            array_push($params, $term, $term, $term, $term, $term, $term, $term);
        }

        $whereClause = implode(' AND ', $where);

        return $this->db->fetchAll(
            "SELECT al.*, u.lrn, u.first_name, u.last_name, u.role AS user_role, u.email
             FROM admin_logs al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE $whereClause
             ORDER BY al.created_at DESC
             LIMIT $limit",
            $params
        );
    }
}
