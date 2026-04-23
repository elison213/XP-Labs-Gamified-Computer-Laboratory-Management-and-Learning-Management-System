<?php
/**
 * XPLabs - User Service
 * Handles user CRUD, import, and point management.
 */

namespace XPLabs\Services;

use XPLabs\Lib\Database;

class UserService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get user by ID.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch("SELECT id, lrn, first_name, last_name, email, role, avatar, is_active, created_at FROM users WHERE id = ?", [$id]);
    }

    /**
     * Get user by LRN.
     */
    public function findByLrn(string $lrn): ?array
    {
        return $this->db->fetch("SELECT * FROM users WHERE lrn = ?", [$lrn]);
    }

    /**
     * Verify credentials for PC override flow and permission.
     */
    public function verifyPcOverrideCredentials(string $identifier, string $password): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return null;
        }

        $user = $this->db->fetch(
            "SELECT id, lrn, email, first_name, last_name, role, password_hash, is_active,
                    COALESCE(can_unlock_pc_override, 0) AS can_unlock_pc_override
             FROM users
             WHERE (lrn = ? OR email = ?) AND is_active = 1
             LIMIT 1",
            [$identifier, $identifier]
        );
        if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        if ((int) ($user['can_unlock_pc_override'] ?? 0) !== 1) {
            return null;
        }
        unset($user['password_hash']);
        return $user;
    }

    /**
     * List users with pagination.
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['role'])) {
            $where[] = 'role = ?';
            $params[] = $filters['role'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(first_name LIKE ? OR last_name LIKE ? OR lrn LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (isset($filters['is_active'])) {
            $where[] = 'is_active = ?';
            $params[] = (int) $filters['is_active'];
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchOne("SELECT COUNT(*) FROM users WHERE $whereClause", $params);
        $users = $this->db->fetchAll("SELECT id, lrn, first_name, last_name, email, role, is_active, last_login, created_at FROM users WHERE $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);

        return [
            'data' => $users,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Create a new user.
     */
    public function create(array $data): int
    {
        $existing = $this->findByLrn($data['lrn']);
        if ($existing) {
            throw new \Exception("User with LRN '{$data['lrn']}' already exists.");
        }

        $passwordHash = password_hash($data['password'] ?? $data['lrn'], PASSWORD_DEFAULT);

        return $this->db->insert('users', [
            'lrn' => $data['lrn'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'role' => $data['role'] ?? 'student',
            'password_hash' => $passwordHash,
            'is_active' => 1,
        ]);
    }

    /**
     * Update a user.
     */
    public function update(int $id, array $data): bool
    {
        $allowed = ['first_name', 'last_name', 'email', 'role', 'is_active'];
        $update = array_intersect_key($data, array_flip($allowed));

        if (empty($update)) {
            return false;
        }

        return $this->db->update('users', $update, 'id = ?', [$id]) > 0;
    }

    /**
     * Delete a user.
     */
    public function delete(int $id): bool
    {
        return $this->db->delete('users', 'id = ?', [$id]) > 0;
    }

    /**
     * Import users from CSV data.
     */
    public function importFromCsv(array $rows, array $columnMapping, string $role = 'student', int $importedBy = 0): array
    {
        $results = ['success' => 0, 'duplicate' => 0, 'error' => 0, 'errors' => []];

        $this->db->beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $lrn = trim($row[$columnMapping['lrn']] ?? '');
                $firstName = trim($row[$columnMapping['first_name']] ?? '');
                $lastName = trim($row[$columnMapping['last_name']] ?? '');

                if (empty($lrn) || empty($firstName) || empty($lastName)) {
                    $results['error']++;
                    $results['errors'][] = "Row " . ($index + 1) . ": Missing required fields";
                    continue;
                }

                $existing = $this->findByLrn($lrn);
                if ($existing) {
                    $results['duplicate']++;
                    continue;
                }

                $this->create([
                    'lrn' => $lrn,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => trim($row[$columnMapping['email']] ?? ''),
                    'role' => $role,
                    'password' => $lrn, // Default password is LRN
                ]);
                $results['success']++;
            }

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }

        return $results;
    }

    /**
     * Preview CSV import without inserting (counts + sample rows).
     */
    public function previewImportFromCsv(array $rows, array $columnMapping): array
    {
        $out = [
            'would_import' => 0,
            'would_duplicate' => 0,
            'invalid' => 0,
            'errors' => [],
            'sample' => [],
        ];

        foreach ($rows as $index => $row) {
            $lrn = trim($row[$columnMapping['lrn']] ?? '');
            $firstName = trim($row[$columnMapping['first_name']] ?? '');
            $lastName = trim($row[$columnMapping['last_name']] ?? '');

            if ($lrn === '' || $firstName === '' || $lastName === '') {
                $out['invalid']++;
                $out['errors'][] = 'Row ' . ($index + 1) . ': Missing required fields';
                continue;
            }

            if ($this->findByLrn($lrn)) {
                $out['would_duplicate']++;
            } else {
                $out['would_import']++;
            }

            if (count($out['sample']) < 25) {
                $out['sample'][] = [
                    'row' => $index + 1,
                    'lrn' => $lrn,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => trim($row[$columnMapping['email'] ?? ''] ?? ''),
                    'would_skip' => $this->findByLrn($lrn) !== null,
                ];
            }
        }

        return $out;
    }

    /**
     * Import users from uploaded CSV file.
     */
    public function importFromFile(string $filePath, string $role = 'student'): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception('File not found');
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception('Cannot open file');
        }

        $results = ['success' => 0, 'duplicate' => 0, 'error' => 0, 'errors' => []];
        $header = fgetcsv($handle); // Skip header row

        $this->db->beginTransaction();
        try {
            $rowNum = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                $lrn = trim($row[0] ?? '');
                $firstName = trim($row[1] ?? '');
                $lastName = trim($row[2] ?? '');
                $email = trim($row[3] ?? '');

                if (empty($lrn) || empty($firstName) || empty($lastName)) {
                    $results['error']++;
                    $results['errors'][] = "Row $rowNum: Missing required fields";
                    continue;
                }

                $existing = $this->findByLrn($lrn);
                if ($existing) {
                    $results['duplicate']++;
                    continue;
                }

                $this->create([
                    'lrn' => $lrn,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email ?: null,
                    'role' => $role,
                    'password' => $lrn,
                ]);
                $results['success']++;
            }

            fclose($handle);
            $this->db->commit();
        } catch (\Exception $e) {
            fclose($handle);
            $this->db->rollback();
            throw $e;
        }

        return $results;
    }

    /**
     * Get user's point balance.
     */
    public function getPointBalance(int $userId): int
    {
        $balance = $this->db->fetchOne(
            "SELECT balance FROM user_point_balances WHERE user_id = ?",
            [$userId]
        );
        return (int) ($balance ?? 0);
    }

    /**
     * Award points to a user.
     */
    public function awardPoints(int $userId, int $points, string $reason, ?string $refType = null, ?int $refId = null): int
    {
        $this->db->insert('user_points', [
            'user_id' => $userId,
            'points' => $points,
            'reason' => $reason,
            'reference_type' => $refType,
            'reference_id' => $refId,
        ]);

        return $this->getPointBalance($userId);
    }

    /**
     * Spend points (deduct from balance).
     */
    public function spendPoints(int $userId, int $points, string $reason, ?string $refType = null, ?int $refId = null): bool
    {
        $balance = $this->getPointBalance($userId);
        if ($balance < $points) {
            return false;
        }

        $this->db->insert('user_points', [
            'user_id' => $userId,
            'points' => -$points,
            'reason' => $reason,
            'reference_type' => $refType,
            'reference_id' => $refId,
        ]);

        return true;
    }

    /**
     * Get user's point history.
     */
    public function getPointHistory(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $total = (int) $this->db->fetchOne("SELECT COUNT(*) FROM user_points WHERE user_id = ?", [$userId]);
        $history = $this->db->fetchAll(
            "SELECT * FROM user_points WHERE user_id = ? ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
            [$userId]
        );

        return [
            'data' => $history,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }
}