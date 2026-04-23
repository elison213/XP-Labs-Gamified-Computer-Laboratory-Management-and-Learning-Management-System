<?php
/**
 * XPLabs API - POST /api/lab/queue-command
 * Queue a remote command for one or all lab PCs.
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

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$pcId = $input['pc_id'] ?? null;
$commandType = $input['command_type'] ?? '';
$issuedBy = (int) (Auth::id() ?? 0);
$params = $input['params'] ?? null;

if (!$commandType) {
    http_response_code(400);
    echo json_encode(['error' => 'command_type is required']);
    exit;
}

$allowedCommands = ['lock', 'unlock', 'shutdown', 'restart', 'message', 'screenshot'];
if (!in_array($commandType, $allowedCommands)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid command type']);
    exit;
}

$pcService = new PCService();
$commandsSent = 0;

if ($pcId === 'all') {
    // Queue command for all online PCs
    $db = \XPLabs\Lib\Database::getInstance();
    $allPCs = $db->fetchAll("SELECT id FROM lab_pcs WHERE status IN ('online', 'idle')");
    
    foreach ($allPCs as $pc) {
        $result = $pcService->queueCommand($pc['id'], $issuedBy, $commandType, $params);
        if ($result['success']) {
            $commandsSent++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Command queued to $commandsSent PCs",
        'commands_sent' => $commandsSent,
    ]);
} else {
    $pcId = (int) $pcId;
    if (!$pcId) {
        http_response_code(400);
        echo json_encode(['error' => 'pc_id is required']);
        exit;
    }
    
    $result = $pcService->queueCommand($pcId, $issuedBy, $commandType, $params);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Command queued',
            'command_id' => $result['command_id'],
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => $result['error']]);
    }
}