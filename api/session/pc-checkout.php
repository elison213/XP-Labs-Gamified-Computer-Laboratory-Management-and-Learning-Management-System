<?php
/**
 * XPLabs API - POST /api/session/pc-checkout
 * Check out a student from a lab PC.
 * Called by PowerShell Logoff.ps1 script on user sign-out.
 */

require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../../services/UserService.php';
require_once __DIR__ . '/../../services/AttendanceService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';
require_once __DIR__ . '/../middleware/MachineAuth.php';

use XPLabs\Services\PCService;
use XPLabs\Services\UserService;
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
$input = json_decode(file_get_contents('php://input'), true);
$lrn = trim($input['lrn'] ?? '');
$reason = trim($input['reason'] ?? 'normal');

if (empty($lrn)) {
    http_response_code(400);
    echo json_encode(['error' => 'LRN is required']);
    exit;
}

$userService = new UserService();
$user = $userService->findByLrn($lrn);

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'Student not found']);
    exit;
}

$pcService = new PCService();
$activeSession = $pcService->getUserActiveSession($user['id']);

if (!$activeSession) {
    http_response_code(400);
    echo json_encode(['error' => 'No active session found for this user']);
    exit;
}
if ((int) $activeSession['pc_id'] !== (int) $pc['id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Active session is bound to another PC']);
    exit;
}

// Calculate session duration
$checkinTime = strtotime($activeSession['checkin_time']);
$checkoutTime = time();
$durationSeconds = $checkoutTime - $checkinTime;
$durationMinutes = round($durationSeconds / 60, 2);

// End PC session
$pcService->endSession($activeSession['id'], $reason);

// End attendance session if applicable
$attendanceService = new AttendanceService();
$attendanceEnded = false;
$db = \XPLabs\Lib\Database::getInstance();
$attendanceRecord = $db->fetch(
    "SELECT * FROM attendance_sessions WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1",
    [$user['id']]
);

if ($attendanceRecord) {
    $attendanceService->endSession($attendanceRecord['id']);
    $attendanceEnded = true;
}

echo json_encode([
    'success' => true,
    'message' => "Goodbye, {$user['first_name']}!",
    'session_id' => $activeSession['id'],
    'checkin_time' => $activeSession['checkin_time'],
    'checkout_time' => date('Y-m-d H:i:s'),
    'duration_minutes' => $durationMinutes,
    'attendance_ended' => $attendanceEnded,
]);