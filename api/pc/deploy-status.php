<?php
/**
 * XPLabs API - GET /api/pc/deploy-status
 * Returns deployment summary and recent jobs.
 */
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
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
$service = new PCService();
$db = Database::getInstance();
$limit = max(1, min(50, (int) ($_GET['limit'] ?? 20)));

$jobs = $db->fetchAll(
    "SELECT j.id, j.pc_id, j.status, j.trigger_type, j.created_at, j.started_at, j.finished_at,
            lp.hostname, lp.ip_address, lp.deployment_status
     FROM pc_deployment_jobs j
     JOIN lab_pcs lp ON lp.id = j.pc_id
     ORDER BY j.created_at DESC
     LIMIT ?",
    [$limit]
);

echo json_encode([
    'success' => true,
    'summary' => $service->getDeploymentStatusSummary(),
    'jobs' => $jobs,
]);
