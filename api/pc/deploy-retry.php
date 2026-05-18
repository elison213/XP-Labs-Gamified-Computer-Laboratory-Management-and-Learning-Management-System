<?php
/**
 * XPLabs API - POST /api/pc/deploy-retry
 * Retry deployment for failed/selected PCs.
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

Auth::requireRole(['admin']);
Csrf::requireValidToken();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$pcId = (int) ($input['pc_id'] ?? 0);
if ($pcId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'pc_id is required']);
    exit;
}

$service = new PCService();
$res = $service->queueDeploymentJob($pcId, 'retry', Auth::id(), ['source' => 'deploy-retry']);
if (empty($res['success'])) {
    http_response_code(400);
    echo json_encode(['error' => $res['error'] ?? 'Retry queue failed']);
    exit;
}

echo json_encode(['success' => true, 'job_id' => $res['job_id'], 'pc_id' => $pcId]);
