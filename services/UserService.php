<?php
/**
 * XPLabs - User Service
 * Handles user CRUD, import, and point management.
 */

namespace XPLabs\Services;

use XPLabs\Lib\Database;
use XPLabs\Lib\PasswordPolicy;

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
     * Verify student LRN + password for lockscreen lab login.
     */
    public function verifyStudentLabCredentials(string $lrn, string $password): ?array
    {
        $lrn = trim($lrn);
        if ($lrn === '' || $password === '') {
            return null;
        }

        $user = $this->db->fetch(
            "SELECT id, lrn, email, first_name, last_name, role, password_hash, is_active,
                    grade_level, section, course_id
             FROM users
             WHERE lrn = ? AND is_active = 1
             LIMIT 1",
            [$lrn]
        );
        if (!$user || ($user['role'] ?? '') !== 'student') {
            return null;
        }
        if (empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
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
            $where[] = '(first_name LIKE ? OR last_name LIKE ? OR lrn LIKE ? OR email LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['section'])) {
            $where[] = 'section = ?';
            $params[] = $filters['section'];
        }

        if (!empty($filters['grade_level'])) {
            $where[] = 'grade_level = ?';
            $params[] = $filters['grade_level'];
        }

        if (isset($filters['is_active'])) {
            $where[] = 'is_active = ?';
            $params[] = (int) $filters['is_active'];
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchOne("SELECT COUNT(*) FROM users WHERE $whereClause", $params);
        $users = $this->db->fetchAll("SELECT id, lrn, first_name, last_name, email, role, is_active, section, grade_level, last_login, created_at FROM users WHERE $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);

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

        $role = $data['role'] ?? 'student';
        $lrn = (string) ($data['lrn'] ?? '');
        if ($role === 'teacher' || $role === 'admin') {
            $pwd = (string) ($data['password'] ?? '');
            if ($pwd === '') {
                throw new \InvalidArgumentException('Password is required for ' . $role . ' accounts.');
            }
            $policyError = PasswordPolicy::validate($pwd, $lrn);
            if ($policyError !== null) {
                throw new \InvalidArgumentException($policyError);
            }
            $passwordHash = password_hash($pwd, PASSWORD_DEFAULT);
        } else {
            $fallback = $lrn;
            $passwordHash = password_hash((string) ($data['password'] ?? $fallback), PASSWORD_DEFAULT);
        }

        $row = [
            'lrn' => $data['lrn'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => !empty($data['email']) ? trim((string) $data['email']) : null,
            'role' => $role,
            'password_hash' => $passwordHash,
            'is_active' => 1,
        ];

        if (array_key_exists('section', $data)) {
            $s = trim((string) ($data['section'] ?? ''));
            $row['section'] = $s !== '' ? $s : null;
        }
        if (array_key_exists('grade_level', $data)) {
            $g = trim((string) ($data['grade_level'] ?? ''));
            $row['grade_level'] = $g !== '' ? $g : null;
        }

        return $this->db->insert('users', $row);
    }

    /**
     * Update a user.
     */
    public function update(int $id, array $data): bool
    {
        $allowed = ['first_name', 'last_name', 'email', 'role', 'is_active', 'section', 'grade_level'];
        $update = array_intersect_key($data, array_flip($allowed));

        foreach (['section', 'grade_level'] as $key) {
            if (array_key_exists($key, $update)) {
                $v = trim((string) $update[$key]);
                $update[$key] = $v === '' ? null : $v;
            }
        }

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
     * Set password (admin reset). Applies policy for teacher/admin; students may use simpler rules when forced.
     */
    public function setPassword(int $id, string $password, bool $enforcePolicy = true): bool
    {
        $user = $this->db->fetch('SELECT id, lrn, role FROM users WHERE id = ?', [$id]);
        if (!$user) {
            throw new \InvalidArgumentException('User not found.');
        }

        $role = (string) ($user['role'] ?? '');
        $lrn = (string) ($user['lrn'] ?? '');

        if ($enforcePolicy && in_array($role, ['teacher', 'admin'], true)) {
            $policyError = PasswordPolicy::validate($password, $lrn);
            if ($policyError !== null) {
                throw new \InvalidArgumentException($policyError);
            }
        } elseif ($enforcePolicy && $role === 'student') {
            if (strlen($password) < PasswordPolicy::MIN_LENGTH) {
                throw new \InvalidArgumentException('Student password must be at least ' . PasswordPolicy::MIN_LENGTH . ' characters.');
            }
            if ($lrn !== '' && hash_equals(strtolower($lrn), strtolower($password))) {
                throw new \InvalidArgumentException('Password cannot be the same as the LRN.');
            }
        }

        return $this->db->update('users', [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ], 'id = ?', [$id]) > 0;
    }

    /**
     * Distinct section values for filters (students).
     *
     * @return string[]
     */
    public function getDistinctSections(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT section FROM users WHERE role = 'student' AND section IS NOT NULL AND section != '' ORDER BY section ASC"
        );
        return array_values(array_filter(array_map(static fn ($r) => (string) ($r['section'] ?? ''), $rows)));
    }

    /**
     * Distinct grade levels for filters (students).
     *
     * @return string[]
     */
    public function getDistinctGradeLevels(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT grade_level FROM users WHERE role = 'student' AND grade_level IS NOT NULL AND grade_level != '' ORDER BY grade_level ASC"
        );
        return array_values(array_filter(array_map(static fn ($r) => (string) ($r['grade_level'] ?? ''), $rows)));
    }

    /**
     * Import users from CSV data.
     */
    public function importFromCsv(array $rows, array $columnMapping, string $role = 'student', int $importedBy = 0): array
    {
        $results = ['success' => 0, 'duplicate' => 0, 'error' => 0, 'errors' => []];

        $emailCol = isset($columnMapping['email']) && $columnMapping['email'] !== null ? (int) $columnMapping['email'] : null;
        $sectionCol = isset($columnMapping['section']) && $columnMapping['section'] !== null ? (int) $columnMapping['section'] : null;

        $this->db->beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $lrn = trim($row[$columnMapping['lrn']] ?? '');
                $firstName = trim($row[$columnMapping['first_name']] ?? '');
                $lastName = trim($row[$columnMapping['last_name']] ?? '');

                if ($lrn === '' || $firstName === '' || $lastName === '') {
                    $results['error']++;
                    $results['errors'][] = 'Row ' . ($index + 1) . ': Missing required fields';
                    continue;
                }

                $existing = $this->findByLrn($lrn);
                if ($existing) {
                    $results['duplicate']++;
                    continue;
                }

                $payload = [
                    'lrn' => $lrn,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $emailCol !== null ? trim((string) ($row[$emailCol] ?? '')) : '',
                    'role' => $role,
                    'password' => $lrn,
                ];
                if ($sectionCol !== null) {
                    $payload['section'] = trim((string) ($row[$sectionCol] ?? ''));
                }

                $this->create($payload);
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

        $emailCol = isset($columnMapping['email']) && $columnMapping['email'] !== null ? (int) $columnMapping['email'] : null;
        $sectionCol = isset($columnMapping['section']) && $columnMapping['section'] !== null ? (int) $columnMapping['section'] : null;

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
                $sample = [
                    'row' => $index + 1,
                    'lrn' => $lrn,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $emailCol !== null ? trim((string) ($row[$emailCol] ?? '')) : '',
                    'would_skip' => $this->findByLrn($lrn) !== null,
                ];
                if ($sectionCol !== null) {
                    $sample['section'] = trim((string) ($row[$sectionCol] ?? ''));
                }
                $out['sample'][] = $sample;
            }
        }

        return $out;
    }

    /**
     * Parse a masterlist-style CSV/XLSX and import rows (LRN, names, Section required in headers).
     *
     * @throws \RuntimeException|\InvalidArgumentException
     */
    public function importFromMasterlistUpload(string $tmpPath, string $originalName, string $role = 'student'): array
    {
        $grid = MasterlistSpreadsheetParser::fileToGrid($tmpPath, $originalName);
        $parsed = MasterlistSpreadsheetParser::extractStudentRows($grid);

        return $this->importFromCsv($parsed['rows'], $parsed['column_mapping'], $role, 0);
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