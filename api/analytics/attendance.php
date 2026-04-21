<?php
/**
 * XPLabs API - GET /api/analytics/attendance
 * Attendance analytics
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::requireRole(['admin', 'teacher']);

$db = Database::getInstance();
$userId = Auth::id();
$role = Auth::role();
$courseId = (int) ($_GET['course_id'] ?? 0);

// Scope attendance by course enrollment when course filter is used.
$where = "1=1";
$params = [];
$enrollmentScope = "";

if ($courseId) {
    if ($role === 'teacher') {
        $owned = (int) $db->fetchOne("SELECT COUNT(*) FROM courses WHERE id = ? AND teacher_id = ?", [$courseId, $userId]);
        if ($owned === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid course access']);
            exit;
        }
    }
    $enrollmentScope = " AND EXISTS (
        SELECT 1
        FROM course_enrollments ce
        WHERE ce.user_id = a.user_id
          AND ce.course_id = ?
          AND ce.status = 'enrolled'
    )";
    $params[] = $courseId;
} else {
    if ($role === 'teacher') {
        $enrollmentScope = " AND EXISTS (
            SELECT 1
            FROM course_enrollments ce
            JOIN courses c ON c.id = ce.course_id
            WHERE ce.user_id = a.user_id
              AND ce.status = 'enrolled'
              AND c.teacher_id = ?
        )";
        $params[] = $userId;
    }
}

// Attendance rate per student
$students = $db->fetchAll(
    "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name,
            COUNT(DISTINCT a.id) as total_sessions,
            COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) as attended
     FROM users u
     LEFT JOIN attendance_sessions a ON u.id = a.user_id AND $where $enrollmentScope
     WHERE u.role = 'student' AND u.is_active = 1
     GROUP BY u.id
     HAVING total_sessions > 0
     ORDER BY attended DESC
     LIMIT 20",
    $params
);

$students = array_map(function($s) {
    $s['rate'] = $s['total_sessions'] > 0 ? round(($s['attended'] / $s['total_sessions']) * 100, 1) : 0;
    return $s;
}, $students);

// Daily attendance trend (last 14 days)
$trend = $db->fetchAll(
    "SELECT DATE(a.clock_in) as date, COUNT(DISTINCT a.user_id) as count
     FROM attendance_sessions a
     WHERE a.clock_in >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
       $enrollmentScope
     GROUP BY DATE(a.clock_in)
     ORDER BY date ASC",
    $params
);

// Overall rate
$rateData = $db->fetchOne(
    "SELECT 
        COUNT(DISTINCT a.id) as total,
        COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) as attended
     FROM attendance_sessions a
     WHERE $where $enrollmentScope",
    $params
);

$rate = $rateData['total'] > 0 ? round(($rateData['attended'] / $rateData['total']) * 100, 1) : 0;

echo json_encode([
    'success' => true,
    'students' => $students,
    'trend' => $trend,
    'rate' => $rate
]);