<?php
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
$limit = max(1, min(100, (int) ($_GET['limit'] ?? 30)));
$service = new PCService();
echo json_encode([
    'success' => true,
    'summary' => $service->getUpdateStatusSummary(),
    'jobs' => $service->getRecentUpdateJobs($limit),
]);
