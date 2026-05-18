<?php
/**
 * XPLabs API - POST /api/attendance/qr-checkout
 * Check out a student via QR code scan.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/AttendanceService.php';
require_once __DIR__ . '/../../services/UserService.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\AttendanceService;
use XPLabs\Services\UserService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$lrn = trim($input['lrn'] ?? '');
$sessionId = (int) ($input['session_id'] ?? 0);

if (empty($lrn)) {
    http_response_code(400);
    echo json_encode(['error' => 'LRN is required']);
    exit;
}

$userService = new UserService();
$user = $userService->findByLrn($lrn);

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'Student not found', 'lrn' => $lrn]);
    exit;
}

$attendanceService = new AttendanceService();

// If no session provided, find active session
if (!$sessionId) {
    $db = \XPLabs\Lib\Database::getInstance();
    $enrollments = $db->fetchAll(
        "SELECT ce.course_id FROM course_enrollments ce WHERE ce.user_id = ? AND ce.status = 'enrolled'",
        [$user['id']]
    );

    foreach ($enrollments as $enrollment) {
        $activeSession = $attendanceService->getActiveSession($enrollment['course_id']);
        if ($activeSession) {
            $sessionId = $activeSession['id'];
            break;
        }
    }
}

if (!$sessionId) {
    http_response_code(400);
    echo json_encode(['error' => 'No active session found']);
    exit;
}

$result = $attendanceService->checkOut($sessionId, $user['id']);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => "Goodbye, {$user['first_name']}!",
        'duration_minutes' => $result['duration_minutes'],
    ]);
} else {
    http_response_code(400);
    echo json_encode(['error' => $result['message']]);
}