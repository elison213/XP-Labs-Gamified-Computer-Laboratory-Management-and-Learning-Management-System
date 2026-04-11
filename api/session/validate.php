<?php
/**
 * XPLabs API - GET /api/session/validate
 * Validate if a student can check in at a PC.
 * Used by PowerShell AutoLock.ps1 to enforce check-in requirements.
 */

require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../../services/UserService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';
require_once __DIR__ . '/../middleware/MachineAuth.php';

use XPLabs\Services\PCService;
use XPLabs\Services\UserService;
use XPLabs\Api\Middleware\CorsMiddleware;
use XPLabs\Api\Middleware\MachineAuth;

header('Content-Type: application/json');
CorsMiddleware::allowLabPCs();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$pc = MachineAuth::require();

$lrn = trim($_GET['lrn'] ?? '');

if (empty($lrn)) {
    http_response_code(400);
    echo json_encode(['error' => 'LRN is required']);
    exit;
}

$userService = new UserService();
$user = $userService->findByLrn($lrn);

if (!$user) {
    echo json_encode([
        'valid' => false,
        'error' => 'Student not found',
        'action' => 'lock_screen',
    ]);
    exit;
}

$pcService = new PCService();

// Check if user has an active PC session
$activeSession = $pcService->getUserActiveSession($user['id']);

if ($activeSession) {
    echo json_encode([
        'valid' => true,
        'user' => [
            'id' => $user['id'],
            'lrn' => $user['lrn'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role' => $user['role'],
        ],
        'session' => [
            'id' => $activeSession['id'],
            'pc_id' => $activeSession['pc_id'],
            'checkin_time' => $activeSession['checkin_time'],
        ],
        'action' => 'allow_access',
    ]);
    exit;
}

// No active session - check grace period
$checkGracePeriod = false;
if (isset($_GET['grace_minutes'])) {
    $graceMinutes = (int) $_GET['grace_minutes'];
    if ($graceMinutes > 0) {
        // Check if user recently checked in at kiosk
        $db = \XPLabs\Lib\Database::getInstance();
        $recentAttendance = $db->fetch(
            "SELECT clock_in FROM attendance_sessions
             WHERE user_id = ? AND clock_in > DATE_SUB(NOW(), INTERVAL ? MINUTE)
             ORDER BY clock_in DESC LIMIT 1",
            [$user['id'], $graceMinutes]
        );

        if ($recentAttendance) {
            $checkGracePeriod = true;
        }
    }
}

if ($checkGracePeriod) {
    echo json_encode([
        'valid' => true,
        'grace_period' => true,
        'message' => 'User is within grace period',
        'action' => 'allow_access',
    ]);
} else {
    echo json_encode([
        'valid' => false,
        'error' => 'No active session found',
        'action' => 'lock_screen',
        'message' => 'Please check in at the kiosk first',
    ]);
}