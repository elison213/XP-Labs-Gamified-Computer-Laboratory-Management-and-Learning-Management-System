<?php
/**
 * XPLabs API - GET/POST /api/pc/commands
 * GET: Get pending commands for a PC
 * POST: Execute/acknowledge a command
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

// Authenticate the PC
$pc = MachineAuth::require();
$pcService = new PCService();

// =====================================================
// GET: Get pending commands
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $afterCursor = isset($_GET['after_cursor']) ? (int) $_GET['after_cursor'] : 0;
    if ($afterCursor < 0) {
        $afterCursor = 0;
    }
    $commands = $pcService->getPendingCommandsAfterCursor((int) $pc['id'], $afterCursor);
    $nextCursor = $afterCursor;
    foreach ($commands as $cmd) {
        $id = (int) ($cmd['id'] ?? 0);
        if ($id > $nextCursor) {
            $nextCursor = $id;
        }
    }
    $pcService->emitProtocolDebugEvent((int) $pc['id'], 'command_cursor_poll', 'info', [
        'pc_id' => (int) $pc['id'],
        'request_cursor' => $afterCursor,
        'next_cursor' => $nextCursor,
        'commands_count' => count($commands),
    ]);

    echo json_encode([
        'success' => true,
        'commands' => array_map(function ($cmd) {
            return [
                'id' => (int) $cmd['id'],
                'type' => $cmd['command_type'],
                'params' => $cmd['params'] ? json_decode($cmd['params'], true) : null,
                'issued_at' => $cmd['created_at'],
                'expires_at' => $cmd['expires_at'],
            ];
        }, $commands),
        'count' => count($commands),
        'next_cursor' => $nextCursor,
    ]);
    exit;
}

// =====================================================
// POST: Execute or report command result
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $commandId = (int) ($input['command_id'] ?? 0);
    $status = $input['status'] ?? 'executed'; // 'executed' or 'failed'
    $result = $input['result'] ?? null;

    if (!$commandId) {
        http_response_code(400);
        echo json_encode(['error' => 'command_id is required']);
        exit;
    }
    if (!in_array($status, ['executed', 'failed'], true)) {
        http_response_code(400);
        echo json_encode(['error' => "status must be 'executed' or 'failed'"]);
        exit;
    }
    if ($result !== null && !is_string($result)) {
        http_response_code(400);
        echo json_encode(['error' => 'result must be a string when provided']);
        exit;
    }

    // Verify the command belongs to this PC
    $db = \XPLabs\Lib\Database::getInstance();
    $command = $db->fetch(
        "SELECT * FROM remote_commands WHERE id = ? AND pc_id = ?",
        [$commandId, $pc['id']]
    );

    if (!$command) {
        http_response_code(404);
        echo json_encode(['error' => 'Command not found']);
        exit;
    }

    if ($command['status'] !== 'pending') {
        $pcService->emitProtocolDebugEvent((int) $pc['id'], 'command_ack_duplicate', 'info', [
            'pc_id' => (int) $pc['id'],
            'command_id' => $commandId,
            'requested_status' => $status,
        ]);
        echo json_encode([
            'success' => true,
            'already_processed' => true,
            'message' => 'Command already processed',
        ]);
        exit;
    }

    // Update command status
    if ($status === 'executed') {
        $pcService->executeCommand($commandId, $result);
    } else {
        $pcService->failCommand($commandId, $result ?? 'Execution failed');
    }
    $pcService->emitProtocolDebugEvent((int) $pc['id'], 'command_ack_processed', 'info', [
        'pc_id' => (int) $pc['id'],
        'command_id' => $commandId,
        'status' => $status,
    ]);

    // If it was a lock/unlock command, update PC status
    if ($command['command_type'] === 'lock') {
        $pcService->updatePCStatus($pc['id'], 'locked');
    } elseif ($command['command_type'] === 'unlock') {
        $pcService->updatePCStatus($pc['id'], 'online');
    }

    echo json_encode([
        'success' => true,
        'message' => "Command marked as $status",
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);