<?php
/**
 * XPLabs API - POST /api/pc/heartbeat
 * MeshCentral-style check-in contract:
 * - idempotent via heartbeat_id
 * - returns ack_id + command cursor + pending commands
 */

require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';
require_once __DIR__ . '/../middleware/MachineAuth.php';

use XPLabs\Services\PCService;
use XPLabs\Api\Middleware\CorsMiddleware;
use XPLabs\Api\Middleware\MachineAuth;

// Set headers
header('Content-Type: application/json');
CorsMiddleware::allowLabPCs();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Authenticate the PC
$pc = MachineAuth::require();

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$pcService = new PCService();
$result = $pcService->processHeartbeatDelivery($pc, $input);

if (!($result['success'] ?? false)) {
    $pcService->emitProtocolDebugEvent((int) ($pc['id'] ?? 0), 'heartbeat_rejected', 'warn', [
        'pc_id' => (int) ($pc['id'] ?? 0),
        'error' => (string) ($result['error'] ?? 'unknown'),
    ]);
    http_response_code(400);
}
echo json_encode($result);