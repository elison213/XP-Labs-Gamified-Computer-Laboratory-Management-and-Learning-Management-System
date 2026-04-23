<?php
/**
 * XPLabs API - POST /api/session/override-unlock
 * Machine-authenticated staff override unlock with rate limiting.
 */

require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/UserService.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';
require_once __DIR__ . '/../middleware/MachineAuth.php';

use XPLabs\Lib\Database;
use XPLabs\Services\UserService;
use XPLabs\Services\PCService;
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
$identifier = trim((string) ($input['identifier'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($identifier === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'identifier and password are required']);
    exit;
}

$windowMinutes = 10;
$maxAttempts = 5;
$lockMinutes = 10;
$now = date('Y-m-d H:i:s');

$attempt = $db->fetch("SELECT * FROM pc_override_attempts WHERE pc_id = ?", [(int) $pc['id']]);
if ($attempt && !empty($attempt['locked_until']) && strtotime($attempt['locked_until']) > time()) {
    http_response_code(429);
    echo json_encode([
        'error' => 'Too many failed attempts',
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
$user = $userService->verifyPcOverrideCredentials($identifier, $password);
if (!$user) {
    $attempts++;
    $lockedUntil = null;
    if ($attempts >= $maxAttempts) {
        $lockedUntil = date('Y-m-d H:i:s', strtotime("+{$lockMinutes} minutes"));
    }
    if ($attempt) {
        $db->query(
            "UPDATE pc_override_attempts
             SET attempts = ?, window_started_at = ?, locked_until = ?
             WHERE pc_id = ?",
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
    echo json_encode(['error' => 'Invalid override credentials']);
    exit;
}

// Reset attempts after successful auth.
if ($attempt) {
    $db->query(
        "UPDATE pc_override_attempts
         SET attempts = 0, window_started_at = ?, locked_until = NULL
         WHERE pc_id = ?",
        [$now, (int) $pc['id']]
    );
}

$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$expiresAt = date('Y-m-d H:i:s', time() + 120);
$db->insert('pc_override_tokens', [
    'token_hash' => $tokenHash,
    'pc_id' => (int) $pc['id'],
    'user_id' => (int) $user['id'],
    'expires_at' => $expiresAt,
]);

$pcService = new PCService();
$queue = $pcService->queueCommand((int) $pc['id'], (int) $user['id'], 'unlock', [
    'override_token' => $token,
    'override_user' => $user['lrn'] ?? '',
], 120);

if (empty($queue['success'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to queue unlock command']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Override unlock approved',
    'expires_at' => $expiresAt,
    'command_id' => (int) ($queue['command_id'] ?? 0),
]);
