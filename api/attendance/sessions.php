<?php
/**
 * XPLabs API - GET /api/attendance/sessions
 * List class attendance sessions (course-scoped) for teachers/admins.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

// #region agent log
$__debugLog = static function (string $runId, string $hypothesisId, string $location, string $message, array $data = []): void {
    file_put_contents(__DIR__ . '/../../debug-10ea95.log', json_encode(['sessionId' => '10ea95', 'runId' => $runId, 'hypothesisId' => $hypothesisId, 'location' => $location, 'message' => $message, 'data' => $data, 'timestamp' => (int) round(microtime(true) * 1000)], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
};
// #endregion

Auth::requireRoles(['admin', 'teacher']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db = Database::getInstance();
$userId = Auth::id();
$role = $_SESSION['user_role'] ?? '';
$courseId = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
$status = trim($_GET['status'] ?? '');

// #region agent log
$__debugLog('initial', 'H4', 'api/attendance/sessions.php:34', 'attendance_sessions_request_received', ['user_id' => (int) $userId, 'role' => $role, 'course_id' => $courseId, 'status' => $status]);
// #endregion

if (!$db->tableExists('attendance_sessions')) {
    echo json_encode(['success' => true, 'sessions' => []]);
    exit;
}

$hasCourseId = $db->fetchOne(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'attendance_sessions' AND column_name = 'course_id'"
);

// Back-compat: older schema (migration 006) is user/station clock-in/out and does not have course_id.
if ((int) $hasCourseId === 0) {
    $params = [];
    $where = ['1=1'];

    if ($status !== '') {
        $where[] = 's.status = ?';
        $params[] = $status;
    }

    // Teachers only see their floor(s) in legacy mode if teacher_id exists on lab_floors (it does in migration 004)
    $hasTeacherId = (int) $db->fetchOne(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'lab_floors' AND column_name = 'teacher_id'"
    );

    if ($role === 'teacher' && $hasTeacherId > 0) {
        $where[] = 'f.teacher_id = ?';
        $params[] = $userId;
    }

    $whereSql = implode(' AND ', $where);

    $sessions = $db->fetchAll(
        "SELECT s.*,
                u.first_name, u.last_name,
                st.station_code,
                f.name as floor_name
         FROM attendance_sessions s
         LEFT JOIN users u ON s.user_id = u.id
         LEFT JOIN lab_stations st ON s.station_id = st.id
         LEFT JOIN lab_floors f ON s.floor_id = f.id
         WHERE $whereSql
         ORDER BY s.clock_in DESC
         LIMIT 200",
        $params
    );

    echo json_encode([
        'success' => true,
        'schema' => 'legacy',
        'sessions' => $sessions,
    ]);
    // #region agent log
    $__debugLog('initial', 'H4', 'api/attendance/sessions.php:87', 'attendance_sessions_legacy_response', ['count' => count($sessions), 'teacher_filtered' => $role === 'teacher' && $hasTeacherId > 0]);
    // #endregion
    exit;
}

$params = [];
$where = ['1=1'];

if ($courseId > 0) {
    $where[] = 's.course_id = ?';
    $params[] = $courseId;
}

if ($status !== '') {
    $where[] = 's.status = ?';
    $params[] = $status;
}

if ($role === 'teacher') {
    $where[] = 'c.teacher_id = ?';
    $params[] = $userId;
}

$whereSql = implode(' AND ', $where);

$sessions = $db->fetchAll(
    "SELECT s.*, c.name as course_name, c.code as course_code,
            u.first_name as teacher_first, u.last_name as teacher_last
     FROM attendance_sessions s
     JOIN courses c ON s.course_id = c.id
     LEFT JOIN users u ON s.created_by = u.id
     WHERE $whereSql
     ORDER BY s.created_at DESC
     LIMIT 200",
    $params
);

// #region agent log
$__debugLog('initial', 'H4', 'api/attendance/sessions.php:123', 'attendance_sessions_course_response', ['count' => count($sessions), 'teacher_filtered' => $role === 'teacher']);
// #endregion
echo json_encode(['success' => true, 'schema' => 'course', 'sessions' => $sessions]);
