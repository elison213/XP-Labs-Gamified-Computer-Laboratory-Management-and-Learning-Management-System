<?php
/**
 * XPLabs API - POST /api/session/force-logout
 * Force logout a student from their PC session.
 * Requires teacher/admin authentication.
 */

require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Csrf;
use XPLabs\Services\PCService;
use XPLabs\Api\Middleware\CorsMiddleware;

header('Content-Type: application/json');
CorsMiddleware::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

Auth::requireRole(['admin', 'teacher']);
Csrf::requireValidToken();

$input = json_decode(file_get_contents('php://input'), true);
$userId = (int) ($input['user_id'] ?? 0);
$lrn = trim($input['lrn'] ?? '');

if (!$userId && !$lrn) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id or lrn is required']);
    exit;
}

$pcService = new PCService();

// Find user if only LRN provided
if (!$userId && $lrn) {
    $db = \XPLabs\Lib\Database::getInstance();
    $user = $db->fetch("SELECT id FROM users WHERE lrn = ?", [$lrn]);
    if ($user) {
        $userId = $user['id'];
    }
}

if (!$userId) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

// End all active sessions for this user
$sessionsEnded = $pcService->endUserSessions($userId, 'forced_logout');

// Queue lock command for the PC they were using
$db = \XPLabs\Lib\Database::getInstance();
$lastSession = $db->fetch(
    "SELECT pc_id FROM pc_sessions WHERE user_id = ? AND status = 'active' ORDER BY checkin_time DESC LIMIT 1",
    [$userId]
);

if ($lastSession) {
    $teacherId = Auth::id() ?? 0;
    $pcService->queueCommand($lastSession['pc_id'], $teacherId, 'lock');
}

echo json_encode([
    'success' => true,
    'message' => "Student logged out. Sessions ended: $sessionsEnded",
    'sessions_ended' => $sessionsEnded,
]);