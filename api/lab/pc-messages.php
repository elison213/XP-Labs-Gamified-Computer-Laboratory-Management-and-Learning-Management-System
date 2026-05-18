<?php
/**
 * GET /api/lab/pc-messages.php?pc_id=1  OR  ?thread_id=1
 * List threads for a PC or messages in a thread (instructor/admin only).
 */
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../../services/PcMessageService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\PcMessageService;
use XPLabs\Api\Middleware\CorsMiddleware;

header('Content-Type: application/json');
CorsMiddleware::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

Auth::requireRole(['admin', 'teacher']);

$pcId = (int) ($_GET['pc_id'] ?? 0);
$threadId = (int) ($_GET['thread_id'] ?? 0);

$svc = new PcMessageService();

if (!$svc->tableExists()) {
    echo json_encode(['success' => true, 'threads' => [], 'messages' => []]);
    exit;
}

if ($threadId > 0) {
    $thread = $svc->getThread($threadId);
    if (!$thread) {
        http_response_code(404);
        echo json_encode(['error' => 'Thread not found']);
        exit;
    }
    $messages = $svc->getMessages($threadId);
    echo json_encode([
        'success' => true,
        'thread' => $thread,
        'messages' => $messages,
    ]);
    exit;
}

if ($pcId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'pc_id or thread_id is required']);
    exit;
}

$threads = $svc->getThreadsForPc($pcId);
echo json_encode(['success' => true, 'threads' => $threads]);
