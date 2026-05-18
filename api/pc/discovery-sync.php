<?php
/**
 * XPLabs API - POST /api/pc/discovery-sync
 * Bulk upsert discovered hosts from server-side network discovery.
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

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
$hosts = $payload['hosts'] ?? [];
if (!is_array($hosts)) {
    http_response_code(400);
    echo json_encode(['error' => 'hosts array is required']);
    exit;
}

$pcService = new PCService();
$created = 0;
$updated = 0;
$failed = 0;
$errors = [];

foreach ($hosts as $idx => $host) {
    if (!is_array($host)) {
        $failed++;
        $errors[] = "Host at index {$idx} is not an object";
        continue;
    }
    $res = $pcService->upsertDiscoveredPc($host);
    if (!empty($res['success'])) {
        if (!empty($res['created'])) {
            $created++;
        } else {
            $updated++;
        }
    } else {
        $failed++;
        $errors[] = $res['error'] ?? "Failed host at index {$idx}";
    }
}

echo json_encode([
    'success' => true,
    'created' => $created,
    'updated' => $updated,
    'failed' => $failed,
    'errors' => $errors,
]);
