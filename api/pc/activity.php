<?php
/**
 * POST /api/pc/activity.php — ingest desktop/session activity from widget or agent.
 * GET  /api/pc/activity.php?since=... — list recent events for this PC (machine key).
 */
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/PcActivityService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';
require_once __DIR__ . '/../middleware/MachineAuth.php';

use XPLabs\Services\PcActivityService;
use XPLabs\Api\Middleware\CorsMiddleware;
use XPLabs\Api\Middleware\MachineAuth;

header('Content-Type: application/json');
CorsMiddleware::allowLabPCs();

$pc = MachineAuth::require();
$pcId = (int) ($pc['id'] ?? 0);
$svc = new PcActivityService();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $since = trim((string) ($_GET['since'] ?? ''));
    $limit = (int) ($_GET['limit'] ?? 100);
    $rows = $svc->listForPc($pcId, $limit, $since !== '' ? $since : null);
    $data = array_map(static function ($row) {
        $payload = $row['event_payload'] ?? null;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : ['raw' => $payload];
        }
        return [
            'id' => (int) $row['id'],
            'event_type' => $row['event_type'],
            'payload' => $payload,
            'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
            'created_at' => $row['created_at'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'events' => $data, 'count' => count($data)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$events = $input['events'] ?? [];
if (!is_array($events)) {
    $events = [];
}

$result = $svc->ingestBatch($pcId, $events);
if (!($result['success'] ?? false)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed']);
    exit;
}

echo json_encode([
    'success' => true,
    'inserted' => (int) ($result['inserted'] ?? 0),
]);
