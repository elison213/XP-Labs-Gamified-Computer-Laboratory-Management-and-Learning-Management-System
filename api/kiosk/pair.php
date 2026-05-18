<?php
/**
 * XPLabs API - POST /api/kiosk/pair
 * Exchange a short pairing code (from Lab Management) for a long-lived kiosk API token.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../services/KioskDeviceService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';

use XPLabs\Services\KioskDeviceService;
use XPLabs\Api\Middleware\CorsMiddleware;

CorsMiddleware::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$code = trim((string) ($input['pairing_code'] ?? $input['code'] ?? ''));

if ($code === '') {
    http_response_code(400);
    echo json_encode(['error' => 'pairing_code is required']);
    exit;
}

$svc = new KioskDeviceService();
$result = $svc->pairWithCode($code);

if (empty($result['success'])) {
    http_response_code(400);
    echo json_encode(['error' => $result['error'] ?? 'Pairing failed']);
    exit;
}

echo json_encode([
    'success' => true,
    'token' => $result['token'],
    'floor_id' => (int) ($result['floor_id'] ?? 0),
    'label' => $result['label'] ?? '',
    'device_id' => (int) ($result['device_id'] ?? 0),
]);
