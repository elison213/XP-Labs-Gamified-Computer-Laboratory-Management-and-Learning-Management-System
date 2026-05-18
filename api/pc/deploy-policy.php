<?php
/**
 * XPLabs API - POST /api/pc/deploy-policy
 * Update per-PC deployment policy flags/tags.
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

$fields = [];
if (array_key_exists('auto_deploy_enabled', $input)) {
    $fields['auto_deploy_enabled'] = (bool) $input['auto_deploy_enabled'];
}
if (array_key_exists('deployment_tag', $input)) {
    $fields['deployment_tag'] = (string) $input['deployment_tag'];
}
if (empty($fields)) {
    http_response_code(400);
    echo json_encode(['error' => 'No policy fields to update']);
    exit;
}

$service = new PCService();
$ok = $service->updateDeploymentPolicy($pcId, $fields);
if (!$ok) {
    http_response_code(400);
    echo json_encode(['error' => 'Policy update failed']);
    exit;
}
$eval = $service->applyAutoDeployPolicyToPc($pcId);

echo json_encode([
    'success' => true,
    'evaluation' => $eval,
]);
