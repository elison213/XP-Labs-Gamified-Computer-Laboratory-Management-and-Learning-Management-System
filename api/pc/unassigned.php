<?php
/**
 * XPLabs API - GET /api/pc/unassigned
 * Returns discovered PCs that are not assigned to floor/station.
 */
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\PCService;
use XPLabs\Api\Middleware\CorsMiddleware;

header('Content-Type: application/json');
CorsMiddleware::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

Auth::requireRole(['admin', 'teacher']);
$pcService = new PCService();
$pcs = $pcService->getUnassignedPcs();

echo json_encode([
    'success' => true,
    'pcs' => $pcs,
    'count' => count($pcs),
]);
