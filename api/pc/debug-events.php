<?php
/**
 * XPLabs API - GET /api/pc/debug-events
 * Admin/teacher diagnostics for protocol debug traces.
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

Auth::requireRole(['admin', 'teacher']);

$service = new PCService();
$filters = [
    'pc_id' => isset($_GET['pc_id']) ? (int) $_GET['pc_id'] : null,
    'event_type' => isset($_GET['event_type']) ? trim((string) $_GET['event_type']) : null,
    'heartbeat_id' => isset($_GET['heartbeat_id']) ? trim((string) $_GET['heartbeat_id']) : null,
    'since' => isset($_GET['since']) ? trim((string) $_GET['since']) : null,
    'until' => isset($_GET['until']) ? trim((string) $_GET['until']) : null,
    'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : 100,
    'offset' => isset($_GET['offset']) ? (int) $_GET['offset'] : 0,
];

$events = $service->listProtocolDebugEvents($filters);

echo json_encode([
    'success' => true,
    'filters' => $filters,
    'count' => count($events),
    'events' => array_map(static function (array $row): array {
        $payload = json_decode((string) ($row['event_payload'] ?? '{}'), true);
        return [
            'id' => (int) ($row['id'] ?? 0),
            'pc_id' => isset($row['pc_id']) ? (int) $row['pc_id'] : null,
            'hostname' => $row['hostname'] ?? null,
            'event_type' => (string) ($row['event_type'] ?? ''),
            'severity' => (string) ($row['severity'] ?? 'info'),
            'payload' => is_array($payload) ? $payload : [],
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }, $events),
]);

