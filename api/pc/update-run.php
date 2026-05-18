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
$limit = max(1, min(20, (int) ($input['limit'] ?? 1)));
$windowsSourceDir = (string) ($input['windows_source_dir'] ?? 'C:\\xampp\\htdocs\\xplabs\\windows');
$updateVersion = trim((string) ($input['version'] ?? ''));

$runnerPath = realpath(__DIR__ . '/../../windows/ops/Run-QueuedUpdate.ps1');
if ($runnerPath === false || !is_file($runnerPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Update runner script not found']);
    exit;
}

$service = new PCService();
$jobs = $service->getQueuedUpdateJobs($limit);
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

    $cmd = sprintf(
        'powershell -NoProfile -ExecutionPolicy Bypass -File %s -TargetHost %s -WindowsSourceDir %s -Version %s 2>&1',
        escapeshellarg($runnerPath),
        escapeshellarg($hostname),
        escapeshellarg($windowsSourceDir),
        escapeshellarg($updateVersion)
    );
    $out = shell_exec($cmd);
    $raw = is_string($out) ? trim($out) : '';
    $decoded = json_decode($raw, true);
    $ok = is_array($decoded) && !empty($decoded['success']);
    $payload = is_array($decoded) ? $decoded : ['success' => false, 'error' => $raw !== '' ? $raw : 'Runner returned no output'];
    $service->completeDeploymentJob($jobId, $ok, $payload, $raw);
    $results[] = [
        'job_id' => $jobId,
        'pc_id' => $pcId,
        'hostname' => $hostname,
        'success' => $ok,
        'result' => $payload,
    ];
}

echo json_encode([
    'success' => true,
    'processed' => count($results),
    'results' => $results,
]);
