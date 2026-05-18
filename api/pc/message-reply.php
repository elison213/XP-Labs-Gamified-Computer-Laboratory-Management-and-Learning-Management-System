<?php
/**
 * POST /api/pc/message-reply.php
 * Student reply from lab PC (machine key header required).
 */
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../../services/PcMessageService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';
require_once __DIR__ . '/../middleware/MachineAuth.php';

use XPLabs\Services\PcMessageService;
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
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$threadId = (int) ($input['thread_id'] ?? 0);
$body = (string) ($input['body'] ?? '');
$lrn = (string) ($input['lrn'] ?? '');

$svc = new PcMessageService();
$result = $svc->addStudentReply($threadId, $pc, $lrn, $body);

if (!($result['success'] ?? false)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed']);
    exit;
}

echo json_encode(['success' => true]);
