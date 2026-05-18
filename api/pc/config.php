<?php
/**
 * XPLabs API - GET /api/pc/config
 * Get configuration for a lab PC.
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

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Authenticate the PC
$pc = MachineAuth::require();

$pcService = new PCService();

// Get PC config with lab context
$config = json_decode($pc['config'] ?? '{}', true) ?: [];

// Get lab/floor info
$floorInfo = null;
$stationInfo = null;
$driveMappings = [];
$folderRules = [];

if ($pc['floor_id']) {
    $db = \XPLabs\Lib\Database::getInstance();
    $floorInfo = $db->fetch("SELECT * FROM lab_floors WHERE id = ?", [$pc['floor_id']]);
}

if ($pc['station_id']) {
    $db = \XPLabs\Lib\Database::getInstance();
    $stationInfo = $db->fetch("SELECT * FROM lab_stations WHERE id = ?", [$pc['station_id']]);
}

// Get drive mappings for a default role (student) - override if user logs in
$driveMappings = $pcService->getDriveMappings('student');

// Get folder rules for this floor
$folderRules = $pcService->getFolderRules($pc['floor_id'] ?? null, 'student');

// Check for pending commands on boot
$pendingCommands = $pcService->getPendingCommands($pc['id']);

echo json_encode([
    'success' => true,
    'pc' => [
        'id' => $pc['id'],
        'hostname' => $pc['hostname'],
        'floor_id' => $pc['floor_id'],
        'station_id' => $pc['station_id'],
        'status' => $pc['status'],
    ],
    'floor' => $floorInfo,
    'station' => $stationInfo,
    'config' => [
        'auto_lock_idle_minutes' => $config['auto_lock_idle_minutes'] ?? 15,
        'check_grace_period_minutes' => $config['check_grace_period_minutes'] ?? 5,
        'heartbeat_interval_seconds' => $config['heartbeat_interval_seconds'] ?? 120,
        'disable_usb' => $config['disable_usb'] ?? false,
        'wallpaper_url' => $config['wallpaper_url'] ?? null,
    ],
    'drive_mappings' => $driveMappings,
    'folder_rules' => $folderRules,
    'pending_commands' => array_map(function ($cmd) {
        return [
            'id' => $cmd['id'],
            'type' => $cmd['command_type'],
            'params' => $cmd['params'] ? json_decode($cmd['params'], true) : null,
        ];
    }, $pendingCommands),
]);