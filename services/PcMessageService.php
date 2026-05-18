<?php

namespace XPLabs\Services;

require_once __DIR__ . '/PCService.php';

use XPLabs\Lib\Database;

class PcMessageService
{
    private Database $db;
    private PCService $pcService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->pcService = new PCService();
    }

    public function tableExists(): bool
    {
        return $this->db->tableExists('pc_message_threads');
    }

    /**
     * Create a thread, store first instructor message, queue remote message command.
     */
    public function startThreadAndNotify(int $pcId, int $instructorUserId, string $body, ?int $stationId = null, int $ttlSeconds = 300): array
    {
        $body = trim($body);
        if ($body === '') {
            return ['success' => false, 'error' => 'Message is required'];
        }
        if (mb_strlen($body) > 2000) {
            return ['success' => false, 'error' => 'Message too long (max 2000 characters)'];
        }

        $pc = $this->db->fetch('SELECT id, station_id FROM lab_pcs WHERE id = ?', [$pcId]);
        if (!$pc) {
            return ['success' => false, 'error' => 'PC not found'];
        }

        if (!$this->tableExists()) {
            return ['success' => false, 'error' => 'PC messaging is not installed. Run database migrations.'];
        }

        $effStation = $stationId ?? (int) ($pc['station_id'] ?? 0) ?: null;

        $this->db->beginTransaction();
        try {
            $threadId = (int) $this->db->insert('pc_message_threads', [
                'pc_id' => $pcId,
                'station_id' => $effStation,
                'started_by_user_id' => $instructorUserId,
            ]);

            $this->db->insert('pc_message_messages', [
                'thread_id' => $threadId,
                'sender_user_id' => $instructorUserId,
                'sender_role' => 'instructor',
                'body' => $body,
            ]);

            $queue = $this->pcService->queueCommand(
                $pcId,
                $instructorUserId,
                'message',
                [
                    'message' => $body,
                    'thread_id' => $threadId,
                ],
                $ttlSeconds
            );

            if (!($queue['success'] ?? false)) {
                throw new \RuntimeException($queue['error'] ?? 'Failed to queue command');
            }

            $this->db->commit();

            return [
                'success' => true,
                'thread_id' => $threadId,
                'command_id' => $queue['command_id'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getThreadsForPc(int $pcId, int $limit = 50): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT t.*, u.first_name, u.last_name
             FROM pc_message_threads t
             JOIN users u ON t.started_by_user_id = u.id
             WHERE t.pc_id = ?
             ORDER BY t.started_at DESC
             LIMIT " . max(1, min(100, $limit)),
            [$pcId]
        );
    }

    public function getThread(int $threadId): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        return $this->db->fetch(
            "SELECT t.*, u.first_name, u.last_name
             FROM pc_message_threads t
             JOIN users u ON t.started_by_user_id = u.id
             WHERE t.id = ?",
            [$threadId]
        );
    }

    public function getMessages(int $threadId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT m.*, u.first_name, u.last_name, u.lrn
             FROM pc_message_messages m
             LEFT JOIN users u ON m.sender_user_id = u.id
             WHERE m.thread_id = ?
             ORDER BY m.created_at ASC",
            [$threadId]
        );
    }

    /**
     * Student reply from lab PC (validated against active pc_sessions).
     */
    public function addStudentReply(int $threadId, array $pcRow, string $lrn, string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return ['success' => false, 'error' => 'Reply is required'];
        }
        if (mb_strlen($body) > 2000) {
            return ['success' => false, 'error' => 'Reply too long (max 2000 characters)'];
        }

        if (!$this->tableExists()) {
            return ['success' => false, 'error' => 'PC messaging is not installed'];
        }

        $thread = $this->db->fetch('SELECT * FROM pc_message_threads WHERE id = ?', [$threadId]);
        if (!$thread) {
            return ['success' => false, 'error' => 'Thread not found'];
        }
        if ((int) $thread['pc_id'] !== (int) $pcRow['id']) {
            return ['success' => false, 'error' => 'Thread does not belong to this PC'];
        }
        if (!empty($thread['closed_at'])) {
            return ['success' => false, 'error' => 'Thread is closed'];
        }

        $lrn = trim($lrn);
        if ($lrn === '') {
            return ['success' => false, 'error' => 'LRN is required to send a reply'];
        }

        $user = $this->db->fetch(
            "SELECT id, role FROM users WHERE lrn = ? AND role = 'student'",
            [$lrn]
        );
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid student LRN'];
        }

        // Prefer active session but allow widget replies when student LRN is valid (machine-key auth).
        $session = $this->db->fetch(
            "SELECT id FROM pc_sessions
             WHERE pc_id = ? AND user_id = ? AND status = 'active'",
            [(int) $pcRow['id'], (int) $user['id']]
        );

        $this->db->insert('pc_message_messages', [
            'thread_id' => $threadId,
            'sender_user_id' => (int) $user['id'],
            'sender_role' => 'student',
            'body' => $body,
        ]);

        return ['success' => true];
    }
}
