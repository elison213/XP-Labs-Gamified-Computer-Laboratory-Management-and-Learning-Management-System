<?php
/**
 * XPLabs API - GET /api/analytics/lab-usage
 * Lab usage analytics
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

$sessionScope = "1=1";
$sessionParams = [];
if ($courseId) {
    if ($role === 'teacher') {
        $owned = (int) $db->fetchOne("SELECT COUNT(*) FROM courses WHERE id = ? AND teacher_id = ?", [$courseId, $userId]);
        if ($owned === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid course access']);
            exit;
        }
    }
    $sessionScope .= " AND EXISTS (
        SELECT 1 FROM course_enrollments ce
        WHERE ce.user_id = a.user_id
          AND ce.course_id = ?
          AND ce.status = 'enrolled'
    )";
    $sessionParams[] = $courseId;
} elseif ($role === 'teacher') {
    $sessionScope .= " AND EXISTS (
        SELECT 1 FROM course_enrollments ce
        JOIN courses c ON c.id = ce.course_id
        WHERE ce.user_id = a.user_id
          AND ce.status = 'enrolled'
          AND c.teacher_id = ?
    )";
    $sessionParams[] = $userId;
}

// Station utilization (hours used in last 30 days)
$stations = $db->fetchAll(
    "SELECT ls.station_code as code,
            COALESCE(SUM(TIMESTAMPDIFF(HOUR, a.clock_in, a.clock_out)), 0) as hours
     FROM lab_stations ls
     LEFT JOIN attendance_sessions a ON ls.id = a.station_id 
        AND a.clock_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND $sessionScope
     GROUP BY ls.id
     ORDER BY ls.station_code",
    $sessionParams
);

// Total possible hours (30 days * 12 hours/day = 360 hours per station)
$totalStations = count($stations);
$totalPossible = $totalStations * 360;
$totalUsed = array_sum(array_column($stations, 'hours'));
$utilization = $totalPossible > 0 ? round(($totalUsed / $totalPossible) * 100, 1) : 0;

// Heatmap data (day of week x hour of day)
$heatmapData = $db->fetchAll(
    "SELECT DAYOFWEEK(a.clock_in) as day, HOUR(a.clock_in) as hour, COUNT(*) as count
     FROM attendance_sessions a
     WHERE a.clock_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
       AND $sessionScope
     GROUP BY DAYOFWEEK(a.clock_in), HOUR(a.clock_in)
     ORDER BY day, hour",
    $sessionParams
);

// Build heatmap matrix (7 days x 12 hours: 7AM-6PM)
$heatmap = [];
for ($d = 1; $d <= 7; $d++) {
    $heatmap[$d - 1] = array_fill(0, 12, 0);
}
foreach ($heatmapData as $row) {
    $dayIdx = $row['day'] - 1; // 1=Sun -> 0
    $hourIdx = $row['hour'] - 7; // 7AM -> 0
    if ($dayIdx >= 0 && $dayIdx < 7 && $hourIdx >= 0 && $hourIdx < 12) {
        $heatmap[$dayIdx][$hourIdx] = (int) $row['count'];
    }
}

// Reorder so Monday is first (MySQL: 1=Sun, 2=Mon, ..., 7=Sat)
$heatmap = array_merge(array_slice($heatmap, 1), array_slice($heatmap, 0, 1));

echo json_encode([
    'success' => true,
    'stations' => $stations,
    'utilization' => $utilization,
    'heatmap' => $heatmap
]);