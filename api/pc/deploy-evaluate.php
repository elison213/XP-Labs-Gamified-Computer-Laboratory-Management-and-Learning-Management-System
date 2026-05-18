<?php
/**
 * XPLabs API - GET /api/pc/deploy-evaluate
 * Evaluate auto-deploy eligibility for one/all PCs.
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

Auth::requireRole(['admin']);
$service = new PCService();
$pcId = (int) ($_GET['pc_id'] ?? 0);
$eligibleOnly = (int) ($_GET['eligible_only'] ?? 0) === 1;

if ($pcId > 0) {
    $res = $service->applyAutoDeployPolicyToPc($pcId);
    if (empty($res['success'])) {
        http_response_code(404);
        echo json_encode(['error' => $res['error'] ?? 'PC not found']);
        exit;
    }
    echo json_encode(['success' => true, 'result' => $res]);
    exit;
}

$rows = $service->listDeploymentCandidates($eligibleOnly);
$results = [];
foreach ($rows as $row) {
    $applied = $service->applyAutoDeployPolicyToPc((int) $row['id']);
    if (!empty($applied['success'])) {
        $results[] = [
            'pc_id' => (int) $row['id'],
            'hostname' => $row['hostname'],
            'eligible' => (bool) $applied['eligible'],
            'status' => $applied['status'],
            'reason' => $applied['reason'],
        ];
    }
}

echo json_encode([
    'success' => true,
    'count' => count($results),
    'results' => $results,
]);
