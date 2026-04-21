<?php
/**
 * XPLabs - Point Service
 * Centralized point management for gamification.
 */

namespace XPLabs\Services;

use XPLabs\Lib\Database;

class PointService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Award points to a user.
     */
    public function awardPoints(int $userId, int $points, string $reason, ?string $refType = null, ?int $refId = null, ?int $awardedBy = null): int
    {
        $this->db->insert('user_points', [
            'user_id' => $userId,
            'points' => $points,
            'reason' => $reason,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'awarded_by' => $awardedBy,
        ]);

        $this->checkAchievements($userId);

        return $this->getBalance($userId);
    }

    /**
     * Deduct points from a user.
     */
    public function deductPoints(int $userId, int $points, string $reason, ?string $refType = null, ?int $refId = null, ?int $awardedBy = null): bool
    {
        $balance = $this->getBalance($userId);
        if ($balance < $points) {
            return false;
        }

        $this->db->insert('user_points', [
            'user_id' => $userId,
            'points' => -$points,
            'reason' => $reason,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'awarded_by' => $awardedBy,
        ]);

        return true;
    }

    /**
     * Get user's current point balance.
     */
    public function getBalance(int $userId): int
    {
        // Ensure balance record exists
        $this->db->query(
            "INSERT IGNORE INTO user_point_balances (user_id) VALUES (?)",
            [$userId]
        );

        $balance = $this->db->fetchOne(
            "SELECT balance FROM user_point_balances WHERE user_id = ?",
            [$userId]
        );

        return (int) ($balance ?? 0);
    }

    /**
     * Get user's total earned points (lifetime).
     */
    public function getTotalEarned(int $userId): int
    {
        $this->db->query(
            "INSERT IGNORE INTO user_point_balances (user_id) VALUES (?)",
            [$userId]
        );

        $total = $this->db->fetchOne(
            "SELECT total_earned FROM user_point_balances WHERE user_id = ?",
            [$userId]
        );

        return (int) ($total ?? 0);
    }

    /**
     * Get point history for a user.
     */
    public function getHistory(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $total = (int) $this->db->fetchOne("SELECT COUNT(*) FROM user_points WHERE user_id = ?", [$userId]);
        $history = $this->db->fetchAll(
            "SELECT up.*, u.first_name, u.last_name as awarded_by_name
             FROM user_points up
             LEFT JOIN users u ON up.awarded_by = u.id
             WHERE up.user_id = ?
             ORDER BY up.created_at DESC
             LIMIT $perPage OFFSET $offset",
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

    /**
     * Check and award achievements for a user.
     */
    public function checkAchievements(int $userId): void
    {
        $achievements = $this->db->fetchAll("SELECT * FROM achievements WHERE is_active = 1");
        $totalPoints = $this->getTotalEarned($userId);

        foreach ($achievements as $achievement) {
            // Skip if already earned
            $exists = $this->db->fetchOne(
                "SELECT COUNT(*) FROM user_achievements WHERE user_id = ? AND achievement_id = ?",
                [$userId, $achievement['id']]
            );
            if ((int) $exists > 0) {
                continue;
            }

            $criteria = json_decode($achievement['criteria'], true);
            if ($this->checkAchievementCriteria($userId, $criteria, $totalPoints)) {
                $this->awardAchievement($userId, $achievement);
            }
        }
    }

    /**
     * Check if a user meets achievement criteria.
     */
    private function checkAchievementCriteria(int $userId, array $criteria, int $totalPoints): bool
    {
        switch ($criteria['type'] ?? '') {
            case 'total_points':
                return $totalPoints >= ($criteria['value'] ?? 0);

            case 'attendance_streak':
                // Check consecutive attendance days
                $streak = $this->getAttendanceStreak($userId);
                return $streak >= ($criteria['value'] ?? 0);

            case 'first_login':
                return $this->db->fetchOne(
                    "SELECT COUNT(*) FROM user_points WHERE user_id = ? AND reason = 'first_login'",
                    [$userId]
                ) == 0;

            case 'quiz_perfect':
                return $this->db->fetchOne(
                    "SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ? AND max_score > 0 AND total_score >= max_score",
                    [$userId]
                ) > 0;

            default:
                return false;
        }
    }

    /**
     * Get user's attendance streak (consecutive days).
     */
    private function getAttendanceStreak(int $userId): int
    {
        $records = $this->db->fetchAll(
            "SELECT DISTINCT DATE(clock_in) as day
             FROM attendance_sessions
             WHERE user_id = ?
             ORDER BY day DESC",
            [$userId]
        );

        if (empty($records)) {
            return 0;
        }

        $streak = 0;
        $expectedDate = new \DateTime('today');

        foreach ($records as $record) {
            $recordDate = new \DateTime($record['day']);
            $diff = $expectedDate->diff($recordDate)->days;

            if ($diff === 0 || $diff === 1) {
                $streak++;
                $expectedDate = $recordDate;
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Award an achievement to a user.
     */
    private function awardAchievement(int $userId, array $achievement): void
    {
        $this->db->insert('user_achievements', [
            'user_id' => $userId,
            'achievement_id' => $achievement['id'],
        ]);

        // Award bonus points
        if ($achievement['points_reward'] > 0) {
            $this->awardPoints($userId, $achievement['points_reward'], 'achievement_bonus', 'achievement', $achievement['id']);
        }

        // Create notification
        $this->db->insert('notifications', [
            'user_id' => $userId,
            'type' => 'achievement',
            'title' => '🏆 Achievement Unlocked!',
            'body' => "You earned: {$achievement['name']} - {$achievement['description']}",
        ]);
    }

    /**
     * Get leaderboard.
     */
    public function getLeaderboard(string $period = 'all_time', ?int $courseId = null, int $limit = 50): array
    {
        $periodValue = match ($period) {
            'daily' => date('Y-m-d'),
            'weekly' => date('Y-W'),
            'monthly' => date('Y-m'),
            default => null,
        };

        if ($periodValue) {
            return $this->db->fetchAll(
                "SELECT lc.*, u.first_name, u.last_name, u.lrn
                 FROM leaderboard_cache lc
                 JOIN users u ON lc.user_id = u.id
                 WHERE lc.period = ? AND lc.period_value = ? AND lc.course_id " . ($courseId ? '= ?' : 'IS NULL') . "
                 ORDER BY lc.rank_position ASC
                 LIMIT $limit",
                $courseId ? [$period, $periodValue, $courseId] : [$period, $periodValue]
            );
        }

        // All-time leaderboard from user_point_balances
        return $this->db->fetchAll(
            "SELECT upb.*, u.first_name, u.last_name, u.lrn
             FROM user_point_balances upb
             JOIN users u ON upb.user_id = u.id
             WHERE u.is_active = 1
             ORDER BY upb.total_earned DESC
             LIMIT $limit"
        );
    }

    /**
     * Get user's rank position.
     */
    public function getUserRank(int $userId, ?int $courseId = null): int
    {
        $balance = $this->getTotalEarned($userId);

        $sql = "SELECT COUNT(*) + 1 FROM user_point_balances upb
                JOIN users u ON upb.user_id = u.id
                WHERE upb.total_earned > ? AND u.is_active = 1";
        $params = [$balance];

        if ($courseId) {
            $sql .= " AND upb.user_id IN (SELECT user_id FROM course_enrollments WHERE course_id = ? AND status = 'enrolled')";
            $params[] = $courseId;
        }

        return (int) $this->db->fetchOne($sql, $params);
    }
}