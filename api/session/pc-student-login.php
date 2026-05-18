<?php
/**
 * XPLabs API - POST /api/session/pc-student-login
 * Machine-authenticated student sign-in from lockscreen (LRN + password).
 */

require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/UserService.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../../services/AttendanceService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';
require_once __DIR__ . '/../middleware/MachineAuth.php';

use XPLabs\Lib\Database;
use XPLabs\Services\UserService;
use XPLabs\Services\PCService;
use XPLabs\Services\AttendanceService;
use XPLabs\Api\Middleware\CorsMiddleware;
use XPLabs\Api\Middleware\MachineAuth;

header('Content-Type: application/json');
CorsMiddleware::allowLabPCs();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$pc = MachineAuth::require();
$db = Database::getInstance();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$lrn = trim((string) ($input['lrn'] ?? ''));
$password = (string) ($input['password'] ?? '');
$stationId = isset($input['station_id']) ? (int) $input['station_id'] : null;

if ($lrn === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'LRN and password are required']);
    exit;
}

$windowMinutes = 10;
$maxAttempts = 8;
$lockMinutes = 10;
$now = date('Y-m-d H:i:s');

$attempt = $db->fetch('SELECT * FROM pc_override_attempts WHERE pc_id = ?', [(int) $pc['id']]);
if ($attempt && !empty($attempt['locked_until']) && strtotime($attempt['locked_until']) > time()) {
    http_response_code(429);
    echo json_encode([
        'error' => 'Too many failed attempts. Try again later or ask your instructor.',
        'locked_until' => $attempt['locked_until'],
    ]);
    exit;
}

$windowStart = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));
$attempts = 0;
if ($attempt && strtotime($attempt['window_started_at']) >= strtotime($windowStart)) {
    $attempts = (int) $attempt['attempts'];
}

$userService = new UserService();
$user = $userService->verifyStudentLabCredentials($lrn, $password);
if (!$user) {
    $attempts++;
    $lockedUntil = null;
    if ($attempts >= $maxAttempts) {
        $lockedUntil = date('Y-m-d H:i:s', strtotime("+{$lockMinutes} minutes"));
    }
    if ($attempt) {
        $db->query(
            'UPDATE pc_override_attempts
             SET attempts = ?, window_started_at = ?, locked_until = ?
             WHERE pc_id = ?',
            [$attempts, $now, $lockedUntil, (int) $pc['id']]
        );
    } else {
        $db->insert('pc_override_attempts', [
            'pc_id' => (int) $pc['id'],
            'attempts' => $attempts,
            'window_started_at' => $now,
            'locked_until' => $lockedUntil,
        ]);
    }

    http_response_code(401);
    echo json_encode(['error' => 'Invalid LRN or password']);
    exit;
}

if ($attempt) {
    $db->query(
        'UPDATE pc_override_attempts
         SET attempts = 0, window_started_at = ?, locked_until = NULL
         WHERE pc_id = ?',
        [$now, (int) $pc['id']]
    );
}

$pcService = new PCService();
$validation = $pcService->validateStudentLabLogin((int) $user['id'], (int) $pc['id']);
if (!$validation['valid']) {
    http_response_code(403);
    echo json_encode([
        'error' => $validation['error'],
        'existing_pc' => $validation['existing_pc'] ?? null,
    ]);
    exit;
}

$pcStationId = isset($pc['station_id']) ? (int) $pc['station_id'] : 0;
if ($stationId && $pcStationId && $stationId !== $pcStationId) {
    http_response_code(403);
    echo json_encode(['error' => 'station_id does not match authenticated PC']);
    exit;
}
if (!$stationId && $pcStationId) {
    $stationId = $pcStationId;
}

$sessionResult = $pcService->createSession((int) $user['id'], (int) $pc['id'], $stationId ?: null);
if (!$sessionResult['success']) {
    http_response_code(400);
    echo json_encode(['error' => $sessionResult['error']]);
    exit;
}

$attendanceService = new AttendanceService();
$attendanceRecorded = false;
$attendancePoints = 0;

$enrollments = $db->fetchAll(
    "SELECT ce.course_id FROM course_enrollments ce WHERE ce.user_id = ? AND ce.status = 'enrolled'",
    [(int) $user['id']]
);

foreach ($enrollments as $enrollment) {
    $activeSession = $attendanceService->getActiveSession($enrollment['course_id']);
    if ($activeSession) {
        $attendanceResult = $attendanceService->checkIn(
            $activeSession['id'],
            (int) $user['id'],
            $stationId ?: (int) ($pc['station_id'] ?? 0)
        );
        if ($attendanceResult['success']) {
            $attendanceRecorded = true;
            $attendancePoints = $attendanceResult['points_earned'] ?? 0;
        }
        break;
    }
}

$driveMappings = $pcService->getDriveMappings('student');
$folderRules = $pcService->getFolderRules((int) ($pc['floor_id'] ?? 0), 'student');

echo json_encode([
    'success' => true,
    'message' => "Welcome, {$user['first_name']}!",
    'session_id' => $sessionResult['session_id'],
    'user' => [
        'id' => $user['id'],
        'lrn' => $user['lrn'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'role' => $user['role'],
        'grade_level' => $user['grade_level'] ?? null,
        'section' => $user['section'] ?? null,
    ],
    'pc' => [
        'id' => $pc['id'],
        'hostname' => $pc['hostname'],
        'floor_id' => $pc['floor_id'] ?? null,
    ],
    'drive_mappings' => $driveMappings,
    'folder_rules' => $folderRules,
    'attendance_recorded' => $attendanceRecorded,
    'attendance_points' => $attendancePoints,
]);
