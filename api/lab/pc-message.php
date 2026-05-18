<?php
/**
 * POST /api/lab/pc-message.php
 * Start a PC message thread and queue agent popup (instructor/admin only).
 */
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../../services/PcMessageService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Csrf;
use XPLabs\Services\PcMessageService;
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

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$pcId = (int) ($input['pc_id'] ?? 0);
$message = (string) ($input['message'] ?? '');
$stationId = isset($input['station_id']) ? (int) $input['station_id'] : null;
if ($stationId !== null && $stationId <= 0) {
    $stationId = null;
}

$uid = (int) (Auth::id() ?? 0);
$svc = new PcMessageService();
$result = $svc->startThreadAndNotify($pcId, $uid, $message, $stationId);

if (!($result['success'] ?? false)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed']);
    exit;
}

echo json_encode([
    'success' => true,
    'thread_id' => $result['thread_id'],
    'command_id' => $result['command_id'] ?? null,
]);
