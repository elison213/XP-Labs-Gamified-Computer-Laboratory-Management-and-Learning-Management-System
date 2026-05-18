<?php

namespace XPLabs\Services;

use XPLabs\Lib\Database;

class PcActivityService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tableExists(): bool
    {
        return $this->db->tableExists('pc_activity_events');
    }

    private function getActiveSessionForPc(int $pcId): ?array
    {
        if (!$this->db->tableExists('pc_sessions')) {
            return null;
        }
        return $this->db->fetch(
            "SELECT user_id, lrn FROM pc_sessions WHERE pc_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1",
            [$pcId]
        ) ?: null;
    }

    /**
     * @param array<int, array{event_type: string, payload?: array, user_id?: int|null, created_at?: string}> $events
     */
    public function ingestBatch(int $pcId, array $events): array
    {
        if (!$this->tableExists()) {
            return ['success' => false, 'error' => 'Activity table missing. Run database migrations.'];
        }
        if ($pcId <= 0) {
            return ['success' => false, 'error' => 'Invalid PC'];
        }

        $sessionUserId = 0;
        $session = $this->getActiveSessionForPc($pcId);
        if ($session) {
            $sessionUserId = (int) ($session['user_id'] ?? 0);
        }

        $inserted = 0;
        $this->db->beginTransaction();
        try {
            foreach ($events as $ev) {
                $type = trim((string) ($ev['event_type'] ?? ''));
                if ($type === '' || strlen($type) > 64) {
                    continue;
                }
                $payload = $ev['payload'] ?? [];
                if (!is_array($payload)) {
                    $payload = ['value' => (string) $payload];
                }
                $userId = isset($ev['user_id']) ? (int) $ev['user_id'] : null;
                if ($userId !== null && $userId <= 0) {
                    $userId = null;
                }
                if ($userId === null && $sessionUserId > 0) {
                    $userId = $sessionUserId;
                }
                $createdAt = trim((string) ($ev['created_at'] ?? ''));
                $row = [
                    'pc_id' => $pcId,
                    'user_id' => $userId,
                    'event_type' => $type,
                    'event_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ];
                if ($createdAt !== '') {
                    $row['created_at'] = $createdAt;
                }
                $this->db->insert('pc_activity_events', $row);
                $inserted++;
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return ['success' => true, 'inserted' => $inserted];
    }

    public function listForPc(int $pcId, int $limit = 100, ?string $since = null): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $params = [$pcId];
        $where = 'pc_id = ?';
        if ($since) {
            $where .= ' AND created_at >= ?';
            $params[] = $since;
        }
        return $this->db->fetchAll(
            "SELECT id, pc_id, user_id, event_type, event_payload, created_at
             FROM pc_activity_events
             WHERE $where
             ORDER BY id DESC
             LIMIT $limit",
            $params
        );
    }

    /**
     * Instructor/admin feed of desktop activity across lab PCs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForDashboard(array $filters = [], int $limit = 200): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $where = ['1=1'];
        $params = [];

        $pcId = (int) ($filters['pc_id'] ?? 0);
        if ($pcId > 0) {
            $where[] = 'e.pc_id = ?';
            $params[] = $pcId;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateFrom !== '') {
            $where[] = 'e.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $where[] = 'e.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $eventType = trim((string) ($filters['event_type'] ?? ''));
        if ($eventType !== '') {
            $where[] = 'e.event_type = ?';
            $params[] = $eventType;
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(p.hostname LIKE ? OR u.lrn LIKE ?
                OR u.first_name LIKE ? OR u.last_name LIKE ? OR e.event_type LIKE ?
                OR e.event_payload LIKE ?)';
            array_push($params, $term, $term, $term, $term, $term, $term);
        }

        $whereClause = implode(' AND ', $where);

        return $this->db->fetchAll(
            "SELECT e.id, e.pc_id, e.user_id, e.event_type, e.event_payload, e.created_at,
                    p.hostname, p.station_id,
                    u.lrn, u.first_name, u.last_name
             FROM pc_activity_events e
             INNER JOIN lab_pcs p ON p.id = e.pc_id
             LEFT JOIN users u ON u.id = e.user_id
             WHERE $whereClause
             ORDER BY e.id DESC
             LIMIT $limit",
            $params
        );
    }

    public static function summarizePayload(?string $json): string
    {
        if ($json === null || $json === '') {
            return '';
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $json;
        }
        $process = trim((string) ($data['process'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        if ($process !== '' && $title !== '') {
            return $process . ' — ' . $title;
        }
        if ($process !== '') {
            return $process;
        }
        if ($title !== '') {
            return $title;
        }
        if (isset($data['idle_seconds'])) {
            return 'idle ' . (int) $data['idle_seconds'] . 's';
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
