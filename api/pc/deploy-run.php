<?php
/**
 * XPLabs API - POST /api/pc/deploy-run
 * Execute queued deployment jobs via PowerShell runner.
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

$service = new PCService();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$limit = max(1, min(20, (int) ($input['limit'] ?? 1)));
$config = require __DIR__ . '/../../config/app.php';
$policyCfg = $config['pc_auto_deploy'] ?? [];
$limit = min($limit, max(1, (int) ($policyCfg['max_parallel_jobs'] ?? 5)));

$windowsSourceShare = (string) ($input['windows_source_share'] ?? '');
if ($windowsSourceShare === '') {
    $windowsSourceShare = (string) ($input['windows_source_dir'] ?? '');
}
if ($windowsSourceShare === '') {
    $windowsSourceShare = 'C:\\xampp\\htdocs\\xplabs\\windows';
}

$runnerPath = realpath(__DIR__ . '/../../windows/ops/Run-QueuedDeployment.ps1');
if ($runnerPath === false || !is_file($runnerPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Deployment runner script not found']);
    exit;
}

$jobs = $service->getQueuedDeploymentJobs($limit);
$results = [];
foreach ($jobs as $job) {
    $jobId = (int) $job['id'];
    $pcId = (int) $job['pc_id'];
    $hostname = trim((string) ($job['hostname'] ?? ''));
    if ($hostname === '') {
        $service->completeDeploymentJob($jobId, false, ['error' => 'Missing hostname on PC record'], '');
        $results[] = ['job_id' => $jobId, 'pc_id' => $pcId, 'success' => false, 'error' => 'Missing hostname'];
        continue;
    }
    $service->markDeploymentJobRunning($jobId);

    $serverBaseUrl = rtrim((string) ($_ENV['XPLABS_SERVER_BASE_URL'] ?? ''), '/');
    if ($serverBaseUrl === '') {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $serverBaseUrl = $scheme . '://' . $host . '/xplabs';
    }

    $floorId = (int) ($job['floor_id'] ?? 0);
    $stationId = (int) ($job['station_id'] ?? 0);
    $cmd = sprintf(
        'powershell -NoProfile -ExecutionPolicy Bypass -File %s -TargetHost %s -ServerBaseUrl %s -WindowsSourceShare %s -FloorId %d -StationId %d 2>&1',
        escapeshellarg($runnerPath),
        escapeshellarg($hostname),
        escapeshellarg($serverBaseUrl),
        escapeshellarg($windowsSourceShare),
        $floorId,
        $stationId
    );
    $out = shell_exec($cmd);
    $raw = is_string($out) ? trim($out) : '';
    $decoded = json_decode($raw, true);
    $ok = is_array($decoded) && !empty($decoded['success']);
    $resultPayload = is_array($decoded) ? $decoded : ['success' => false, 'error' => $raw !== '' ? $raw : 'Runner returned no output'];
    $service->completeDeploymentJob($jobId, $ok, $resultPayload, $raw);

    $results[] = [
        'job_id' => $jobId,
        'pc_id' => $pcId,
        'hostname' => $hostname,
        'success' => $ok,
        'result' => $resultPayload,
    ];
}

echo json_encode([
    'success' => true,
    'processed' => count($results),
    'results' => $results,
]);
