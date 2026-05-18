<?php
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
$version = trim((string) ($input['version'] ?? ''));

if (!is_array($pcIds) || empty($pcIds)) {
    http_response_code(400);
    echo json_encode(['error' => 'pc_ids array is required']);
    exit;
}

$service = new PCService();
$queued = [];
$skipped = [];
foreach ($pcIds as $rawId) {
    $pcId = (int) $rawId;
    if ($pcId <= 0) {
        continue;
    }
    $pc = $service->getPCById($pcId);
    if (!$pc) {
        $skipped[] = ['pc_id' => $pcId, 'reason' => 'PC not found'];
        continue;
    }
    $eval = $service->evaluateAutoDeployEligibility($pc);
    if (empty($eval['eligible'])) {
        $skipped[] = ['pc_id' => $pcId, 'reason' => $eval['reason'] ?? 'Excluded by policy'];
        continue;
    }
    if ($dryRun) {
        $queued[] = ['pc_id' => $pcId, 'dry_run' => true];
        continue;
    }
    $res = $service->queueUpdateJob($pcId, $triggerType, Auth::id(), ['source' => 'update-queue', 'version' => $version]);
    if (!empty($res['success'])) {
        $queued[] = ['pc_id' => $pcId, 'job_id' => $res['job_id']];
    } else {
        $skipped[] = ['pc_id' => $pcId, 'reason' => $res['error'] ?? 'Failed to queue update'];
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
