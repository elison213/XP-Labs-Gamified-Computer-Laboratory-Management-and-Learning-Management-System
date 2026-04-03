<?php
/**
 * XPLabs API - POST /api/attendance/start-session
 * Start a new attendance session.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/AttendanceService.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\AttendanceService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

Auth::requireRoles(['teacher', 'admin']);

$input = json_decode(file_get_contents('php://input'), true);
$courseId = (int) ($input['course_id'] ?? ($_POST['course_id'] ?? 0));
$name = trim($input['name'] ?? $_POST['name'] ?? '');

if (!$courseId) {
    http_response_code(400);
    echo json_encode(['error' => 'course_id is required']);
    exit;
}

$attendanceService = new AttendanceService();

try {
    $sessionId = $attendanceService->createSession($courseId, Auth::id(), $name ?: null);
    echo json_encode(['success' => true, 'session_id' => $sessionId]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}