<?php
/**
 * XPLabs API - POST /api/pc/register
 * Register a lab PC with the system.
 * Returns a machine key for subsequent authenticated requests.
 */

require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';

use XPLabs\Lib\Database;
use XPLabs\Services\PCService;
use XPLabs\Api\Middleware\CorsMiddleware;

// Set headers
header('Content-Type: application/json');

// Allow CORS for registration (first contact doesn't have a key yet)
CorsMiddleware::allowLabPCs();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
$hostname = trim($input['hostname'] ?? '');
$ipAddress = trim($input['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
$macAddress = trim($input['mac_address'] ?? '');
$floorId = isset($input['floor_id']) ? (int) $input['floor_id'] : null;
$stationId = isset($input['station_id']) ? (int) $input['station_id'] : null;

if (empty($hostname)) {
    http_response_code(400);
    echo json_encode(['error' => 'Hostname is required']);
    exit;
}

// Register the PC
$pcService = new PCService();
$result = $pcService->registerPC([
    'hostname' => $hostname,
    'ip_address' => $ipAddress,
    'mac_address' => $macAddress,
    'floor_id' => $floorId,
    'station_id' => $stationId,
]);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'pc_id' => $result['pc_id'],
        'machine_key' => $result['machine_key'],
        'message' => $result['message'],
        'next_steps' => [
            'Store the machine_key securely on this PC',
            'Include X-Machine-Key header in all future API requests',
            'Call /api/pc/config to get lab-specific settings',
        ],
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'error' => $result['error'],
    ]);
}