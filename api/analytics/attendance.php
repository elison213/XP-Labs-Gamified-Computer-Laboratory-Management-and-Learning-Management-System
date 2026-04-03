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
$courseId = (int) ($_GET['course_id'] ?? 0);

// Build where clause
$where = "1=1";
$params = [];

if ($courseId) {
    $where .= " AND sa.course_id = ?";
    $params[] = $courseId;
} else {
    $role = $_SESSION['user_role'];
    if ($role === 'teacher') {
        $where .= " AND sa.course_id IN (SELECT id FROM courses WHERE teacher_id = ?)";
        $params[] = $userId;
    }
}

// Attendance rate per student
$students = $db->fetchAll(
    "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name,
            COUNT(DISTINCT sa.id) as total_sessions,
            COUNT(DISTINCT CASE WHEN sa.status = 'completed' THEN sa.id END) as attended
     FROM users u
     LEFT JOIN station_assignments sa ON u.id = sa.user_id AND $where
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
    "SELECT DATE(clock_in) as date, COUNT(DISTINCT user_id) as count
     FROM attendance_sessions
     WHERE clock_in >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
     GROUP BY DATE(clock_in)
     ORDER BY date ASC",
    []
);

// Overall rate
$rateData = $db->fetchOne(
    "SELECT 
        COUNT(DISTINCT sa.id) as total,
        COUNT(DISTINCT CASE WHEN sa.status = 'completed' THEN sa.id END) as attended
     FROM station_assignments sa
     WHERE $where",
    $params
);

$rate = $rateData['total'] > 0 ? round(($rateData['attended'] / $rateData['total']) * 100, 1) : 0;

echo json_encode([
    'success' => true,
    'students' => $students,
    'trend' => $trend,
    'rate' => $rate
]);