<?php
/**
 * GET /api/pc/messages.php — inbox for student widget (machine key).
 * Returns open threads + messages and active session context.
 */
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../../services/PcMessageService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';
require_once __DIR__ . '/../middleware/MachineAuth.php';

use XPLabs\Services\PcMessageService;
use XPLabs\Services\PCService;
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
$pcId = (int) ($pc['id'] ?? 0);

$msgSvc = new PcMessageService();
$pcSvc = new PCService();

if (!$msgSvc->tableExists()) {
    echo json_encode([
        'success' => true,
        'threads' => [],
        'active_session' => null,
        'messaging_enabled' => false,
    ]);
    exit;
}

$threads = $msgSvc->getThreadsForPc($pcId, 20);
$outThreads = [];
foreach ($threads as $t) {
    $tid = (int) ($t['id'] ?? 0);
    if ($tid <= 0) {
        continue;
    }
    $messages = $msgSvc->getMessages($tid);
    $outThreads[] = [
        'id' => $tid,
        'started_at' => $t['started_at'] ?? null,
        'closed_at' => $t['closed_at'] ?? null,
        'instructor' => trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')),
        'messages' => array_map(static function ($m) {
            return [
                'id' => (int) ($m['id'] ?? 0),
                'sender_role' => $m['sender_role'] ?? '',
                'body' => $m['body'] ?? '',
                'created_at' => $m['created_at'] ?? null,
                'first_name' => $m['first_name'] ?? '',
                'last_name' => $m['last_name'] ?? '',
            ];
        }, $messages),
    ];
}

$active = $pcSvc->getActiveSession($pcId);
$activeOut = null;
if ($active) {
    $activeOut = [
        'user_id' => (int) ($active['user_id'] ?? 0),
        'lrn' => $active['lrn'] ?? '',
        'first_name' => $active['first_name'] ?? '',
        'last_name' => $active['last_name'] ?? '',
        'checkin_time' => $active['checkin_time'] ?? null,
    ];
}

echo json_encode([
    'success' => true,
    'messaging_enabled' => true,
    'threads' => $outThreads,
    'active_session' => $activeOut,
]);
