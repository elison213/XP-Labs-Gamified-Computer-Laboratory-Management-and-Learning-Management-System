<?php
/**
 * XPLabs API - POST /api/pc/assign
 * Assign discovered/unassigned PC to floor/station.
 */
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
$pcId = (int) ($input['pc_id'] ?? 0);
$floorId = (int) ($input['floor_id'] ?? 0);
$stationId = isset($input['station_id']) ? (int) $input['station_id'] : null;

if ($pcId <= 0 || $floorId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'pc_id and floor_id are required']);
    exit;
}

try {
    $service = new PCService();
    $ok = $service->assignPc($pcId, $floorId, $stationId > 0 ? $stationId : null);
    echo json_encode(['success' => (bool) $ok]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
