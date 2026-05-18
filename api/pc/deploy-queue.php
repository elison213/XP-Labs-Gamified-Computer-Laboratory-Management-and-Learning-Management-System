<?php
/**
 * XPLabs API - POST /api/pc/deploy-queue
 * Queue deployment jobs for eligible PCs.
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
$pcIds = $input['pc_ids'] ?? [];
$triggerType = (string) ($input['trigger_type'] ?? 'manual');
$dryRun = (bool) ($input['dry_run'] ?? false);
if (!is_array($pcIds) || empty($pcIds)) {
    http_response_code(400);
    echo json_encode(['error' => 'pc_ids array is required']);
    exit;
}

$config = require __DIR__ . '/../../config/app.php';
$policyCfg = $config['pc_auto_deploy'] ?? [];
$maxBulk = max(1, (int) ($policyCfg['max_bulk_jobs_per_request'] ?? 25));
if (count($pcIds) > $maxBulk) {
    http_response_code(400);
    echo json_encode(['error' => "pc_ids exceeds max_bulk_jobs_per_request ($maxBulk)"]);
    exit;
}

$service = new PCService();
$createdBy = Auth::id();
$queued = [];
$skipped = [];

foreach ($pcIds as $rawId) {
    $pcId = (int) $rawId;
    if ($pcId <= 0) {
        continue;
    }
    $eval = $service->applyAutoDeployPolicyToPc($pcId);
    if (empty($eval['success']) || empty($eval['eligible'])) {
        $skipped[] = ['pc_id' => $pcId, 'reason' => $eval['reason'] ?? 'Not eligible'];
        continue;
    }
    if ($dryRun) {
        $queued[] = ['pc_id' => $pcId, 'dry_run' => true];
        continue;
    }
    $res = $service->queueDeploymentJob($pcId, $triggerType, $createdBy, ['source' => 'deploy-queue']);
    if (!empty($res['success'])) {
        $queued[] = ['pc_id' => $pcId, 'job_id' => $res['job_id']];
    } else {
        $skipped[] = ['pc_id' => $pcId, 'reason' => $res['error'] ?? 'Failed to queue'];
    }
}

echo json_encode([
    'success' => true,
    'queued' => $queued,
    'skipped' => $skipped,
    'count_queued' => count($queued),
    'count_skipped' => count($skipped),
    'dry_run' => $dryRun,
]);
