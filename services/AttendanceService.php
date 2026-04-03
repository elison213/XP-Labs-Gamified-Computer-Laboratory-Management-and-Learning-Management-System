<?php
/**
 * XPLabs - Attendance Service
 * Handles session management, QR check-in/out, and attendance tracking.
 */

namespace XPLabs\Services;

use XPLabs\Lib\Database;
use XPLabs\Lib\Auth;

class AttendanceService
{
    private Database $db;
    private PointService $pointService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->pointService = new PointService();
    }

    /**
     * Create a new attendance session.
     */
    public function createSession(int $courseId, int $createdBy, ?string $name = null): int
    {
        $sessionName = $name ?? 'Lab Session - ' . date('Y-m-d H:i');

        return $this->db->insert('attendance_sessions', [
            'course_id' => $courseId,
            'name' => $sessionName,
            'status' => 'active',
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Get active session for a course.
     */
    public function getActiveSession(int $courseId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM attendance_sessions WHERE course_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1",
            [$courseId]
        );
    }

    /**
     * Check in a student to a session.
     */
    public function checkIn(int $sessionId, int $userId, ?int $stationId = null): array
    {
        $session = $this->db->fetch("SELECT * FROM attendance_sessions WHERE id = ?", [$sessionId]);
        if (!$session) {
            return ['success' => false, 'message' => 'Session not found'];
        }

        if ($session['status'] !== 'active') {
            return ['success' => false, 'message' => 'Session is not active'];
        }

        // Check if already checked in
        $existing = $this->db->fetch(
            "SELECT * FROM station_assignments WHERE session_id = ? AND user_id = ? AND checkout_time IS NULL",
            [$sessionId, $userId]
        );
        if ($existing) {
            return ['success' => false, 'message' => 'Already checked in'];
        }

        $this->db->insert('station_assignments', [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'station_id' => $stationId,
            'checkin_time' => date('Y-m-d H:i:s'),
        ]);

        // Award attendance points
        $config = require __DIR__ . '/../config/app.php';
        $points = $config['points']['attendance_clock_in'] ?? 5;

        // Bonus for early arrival (before 8 AM)
        if (date('H') < 8) {
            $points += $config['points']['attendance_on_time_bonus'] ?? 2;
        }

        $this->pointService->awardPoints($userId, $points, 'attendance', 'attendance_session', $sessionId);

        // Update station status
        if ($stationId) {
            $this->db->update('lab_stations', ['status' => 'active'], 'id = ?', [$stationId]);
        }

        return ['success' => true, 'points_earned' => $points];
    }

    /**
     * Check out a student from a session.
     */
    public function checkOut(int $sessionId, int $userId): array
    {
        $assignment = $this->db->fetch(
            "SELECT * FROM station_assignments WHERE session_id = ? AND user_id = ? AND checkout_time IS NULL",
            [$sessionId, $userId]
        );

        if (!$assignment) {
            return ['success' => false, 'message' => 'No active check-in found'];
        }

        $checkoutTime = date('Y-m-d H:i:s');
        $this->db->update('station_assignments', [
            'checkout_time' => $checkoutTime,
        ], 'id = ?', [$assignment['id']]);

        // Award points for full session (45+ minutes)
        $checkinTime = strtotime($assignment['checkin_time']);
        $checkoutTimestamp = strtotime($checkoutTime);
        $durationMinutes = ($checkoutTimestamp - $checkinTime) / 60;

        $config = require __DIR__ . '/../config/app.php';
        if ($durationMinutes >= 45) {
            $points = $config['points']['attendance_full_session'] ?? 3;
            $this->pointService->awardPoints($userId, $points, 'attendance_full_session', 'attendance_session', $sessionId);
        }

        // Free up the station
        if ($assignment['station_id']) {
            $this->db->update('lab_stations', ['status' => 'offline'], 'id = ?', [$assignment['station_id']]);
        }

        return ['success' => true, 'duration_minutes' => round($durationMinutes)];
    }

    /**
     * Get session attendance list.
     */
    public function getSessionAttendance(int $sessionId): array
    {
        return $this->db->fetchAll(
            "SELECT sa.*, u.first_name, u.last_name, u.lrn, ls.station_code
             FROM station_assignments sa
             JOIN users u ON sa.user_id = u.id
             LEFT JOIN lab_stations ls ON sa.station_id = ls.id
             WHERE sa.session_id = ?
             ORDER BY sa.checkin_time",
            [$sessionId]
        );
    }

    /**
     * Close a session.
     */
    public function closeSession(int $sessionId): bool
    {
        // Auto-checkout all remaining students
        $this->db->query(
            "UPDATE station_assignments SET checkout_time = NOW() WHERE session_id = ? AND checkout_time IS NULL",
            [$sessionId]
        );

        return $this->db->update('attendance_sessions', ['status' => 'closed'], 'id = ?', [$sessionId]) > 0;
    }

    /**
     * Get attendance history for a user.
     */
    public function getUserAttendance(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $total = (int) $this->db->fetchOne("SELECT COUNT(*) FROM station_assignments WHERE user_id = ?", [$userId]);
        $records = $this->db->fetchAll(
            "SELECT sa.*, s.name as session_name, ls.station_code
             FROM station_assignments sa
             JOIN attendance_sessions s ON sa.session_id = s.id
             LEFT JOIN lab_stations ls ON sa.station_id = ls.id
             WHERE sa.user_id = ?
             ORDER BY sa.checkin_time DESC
             LIMIT $perPage OFFSET $offset",
            [$userId]
        );

        return [
            'data' => $records,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Get attendance statistics for a session.
     */
    public function getSessionStats(int $sessionId): array
    {
        $total = (int) $this->db->fetchOne("SELECT COUNT(*) FROM station_assignments WHERE session_id = ?", [$sessionId]);
        $present = (int) $this->db->fetchOne("SELECT COUNT(*) FROM station_assignments WHERE session_id = ? AND checkout_time IS NULL", [$sessionId]);
        $completed = (int) $this->db->fetchOne("SELECT COUNT(*) FROM station_assignments WHERE session_id = ? AND checkout_time IS NOT NULL", [$sessionId]);

        return [
            'total' => $total,
            'present' => $present,
            'completed' => $completed,
        ];
    }
}