<?php
/**
 * XPLabs API - POST /api/session/pc-checkin
 * Check in a student at a specific lab PC.
 * Called by PowerShell Logon.ps1 script on user sign-in.
 */

require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../../services/UserService.php';
require_once __DIR__ . '/../../services/AttendanceService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';
require_once __DIR__ . '/../middleware/MachineAuth.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\PCService;
use XPLabs\Services\UserService;
use XPLabs\Services\AttendanceService;
use XPLabs\Api\Middleware\CorsMiddleware;
use XPLabs\Api\Middleware\MachineAuth;

// Set headers
header('Content-Type: application/json');
CorsMiddleware::allowLabPCs();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Authenticate the PC
$pc = MachineAuth::require();

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
$lrn = trim($input['lrn'] ?? '');
$stationId = isset($input['station_id']) ? (int) $input['station_id'] : null;

if (empty($lrn)) {
    http_response_code(400);
    echo json_encode(['error' => 'LRN (student ID) is required']);
    exit;
}

// Find user by LRN
$userService = new UserService();
$user = $userService->findByLrn($lrn);

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'Student not found', 'lrn' => $lrn]);
    exit;
}

if ($user['role'] !== 'student') {
    http_response_code(400);
    echo json_encode(['error' => 'Only students can check in via this endpoint']);
    exit;
}

// Check if PC is available
$pcService = new PCService();

if ($pc['status'] === 'maintenance' || $pc['status'] === 'locked') {
    http_response_code(403);
    echo json_encode([
        'error' => 'PC is not available',
        'status' => $pc['status'],
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

// Validate check-in
$validation = $pcService->validateCheckIn($user['id'], $pc['id']);

if (!$validation['valid']) {
    http_response_code(403);
    echo json_encode([
        'error' => $validation['error'],
        'existing_pc' => $validation['existing_pc'] ?? null,
    ]);
    exit;
}

// Create PC session
$sessionResult = $pcService->createSession($user['id'], $pc['id'], $stationId);

if (!$sessionResult['success']) {
    http_response_code(400);
    echo json_encode(['error' => $sessionResult['error']]);
    exit;
}

// Also record attendance if there's an active class session
$attendanceService = new AttendanceService();
$attendanceRecorded = false;
$attendancePoints = 0;

// Find active session for user's course
$db = \XPLabs\Lib\Database::getInstance();
$enrollments = $db->fetchAll(
    "SELECT ce.course_id FROM course_enrollments ce WHERE ce.user_id = ? AND ce.status = 'enrolled'",
    [$user['id']]
);

foreach ($enrollments as $enrollment) {
    $activeSession = $attendanceService->getActiveSession($enrollment['course_id']);
    if ($activeSession) {
        $attendanceResult = $attendanceService->checkIn(
            $activeSession['id'],
            $user['id'],
            $stationId ?: $pc['station_id']
        );
        if ($attendanceResult['success']) {
            $attendanceRecorded = true;
            $attendancePoints = $attendanceResult['points_earned'] ?? 0;
        }
        break;
    }
}

// Get drive mappings and folder rules for this user's role
$driveMappings = $pcService->getDriveMappings($user['role']);
$folderRules = $pcService->getFolderRules($pc['floor_id'], $user['role']);

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
        'floor_id' => $pc['floor_id'],
    ],
    'drive_mappings' => $driveMappings,
    'folder_rules' => $folderRules,
    'attendance_recorded' => $attendanceRecorded,
    'attendance_points' => $attendancePoints,
]);