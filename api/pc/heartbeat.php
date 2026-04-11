<?php
/**
 * XPLabs API - POST /api/pc/heartbeat
 * Report PC status and receive pending commands.
 * Requires X-Machine-Key header authentication.
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
$status = $input['status'] ?? 'online';
$activeUsers = $input['active_users'] ?? [];
$systemInfo = $input['system_info'] ?? null; // CPU, RAM, disk usage

// Update heartbeat
$pcService = new PCService();
$pcService->updateHeartbeat($pc['id']);

// Update status if provided
if (in_array($status, ['online', 'idle', 'locked'])) {
    $pcService->updatePCStatus($pc['id'], $status);
}

// Store system info if provided
if ($systemInfo) {
    $config = json_decode($pc['config'] ?? '{}', true) ?: [];
    $config['last_system_info'] = $systemInfo;
    $config['last_heartbeat_data'] = $input;
    $db = \XPLabs\Lib\Database::getInstance();
    $db->update('lab_pcs', ['config' => json_encode($config)], 'id = ?', [$pc['id']]);
}

// Get pending commands
$pendingCommands = $pcService->getPendingCommands($pc['id']);

// If there's an active session, include session info
$activeSession = $pcService->getActiveSession($pc['id']);

echo json_encode([
    'success' => true,
    'pc_id' => $pc['id'],
    'hostname' => $pc['hostname'],
    'commands' => array_map(function ($cmd) {
        return [
            'id' => $cmd['id'],
            'type' => $cmd['command_type'],
            'params' => $cmd['params'] ? json_decode($cmd['params'], true) : null,
            'issued_at' => $cmd['created_at'],
        ];
    }, $pendingCommands),
    'active_session' => $activeSession ? [
        'session_id' => $activeSession['id'],
        'user_id' => $activeSession['user_id'],
        'user_name' => trim($activeSession['first_name'] . ' ' . $activeSession['last_name']),
        'lrn' => $activeSession['lrn'],
        'checkin_time' => $activeSession['checkin_time'],
    ] : null,
    'server_time' => date('Y-m-d H:i:s'),
]);