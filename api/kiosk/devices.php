<?php
/**
 * XPLabs API - /api/kiosk/devices
 * CRUD for kiosk phone registrations (admin / teacher session + CSRF on mutations).
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../services/KioskDeviceService.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Csrf;
use XPLabs\Services\KioskDeviceService;

Auth::requireRoles(['admin', 'teacher']);

$svc = new KioskDeviceService();

function kioskAbsolutePairUrl(string $pairingCode): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $appBase = dirname(dirname(dirname($script)));

    return $scheme . '://' . $host . rtrim($appBase, '/') . '/kiosk_mobile.php?pair=' . rawurlencode($pairingCode);
}

if (!$svc->tableExists()) {
    http_response_code(503);
    echo json_encode(['error' => 'kiosk_devices table not found; run database migrations']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $list = $svc->listDevices();
    $out = [];
    foreach ($list as $r) {
        $hasToken = !empty($r['token_hash']);
        unset($r['token_hash'], $r['pairing_code_hash']);
        $r['has_token'] = $hasToken ? 1 : 0;
        $out[] = $r;
    }
    echo json_encode(['success' => true, 'devices' => $out]);
    exit;
}

Csrf::requireValidToken();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if ($method === 'POST') {
    $action = $input['action'] ?? 'create';
    if ($action === 'rotate_token') {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'id is required']);
            exit;
        }
        $res = $svc->rotateApiToken($id);
        if (empty($res['success'])) {
            http_response_code(400);
            echo json_encode(['error' => $res['error'] ?? 'Rotate failed']);
            exit;
        }
        echo json_encode(['success' => true, 'token' => $res['token']]);
        exit;
    }
    if ($action === 'pairing_code') {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'id is required']);
            exit;
        }
        $res = $svc->createPairingCode($id);
        if (empty($res['success'])) {
            http_response_code(400);
            echo json_encode(['error' => $res['error'] ?? 'Failed']);
            exit;
        }
        $pairUrl = kioskAbsolutePairUrl($res['pairing_code']);
        echo json_encode([
            'success' => true,
            'pairing_code' => $res['pairing_code'],
            'expires_at' => $res['expires_at'],
            'pair_url' => $pairUrl,
        ]);
        exit;
    }

    // create
    $label = trim((string) ($input['label'] ?? ''));
    $mac = (string) ($input['mac_address'] ?? '');
    $floorId = (int) ($input['floor_id'] ?? 0);
    $res = $svc->createDevice($label, $mac, $floorId);
    if (empty($res['success'])) {
        http_response_code(400);
        echo json_encode(['error' => $res['error'] ?? 'Create failed']);
        exit;
    }
    echo json_encode(['success' => true, 'id' => (int) $res['id']]);
    exit;
}

if ($method === 'PATCH') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'id is required']);
        exit;
    }
    $patch = [];
    if (array_key_exists('label', $input)) {
        $patch['label'] = $input['label'];
    }
    if (array_key_exists('mac_address', $input)) {
        $patch['mac_address'] = $input['mac_address'];
    }
    if (array_key_exists('floor_id', $input)) {
        $patch['floor_id'] = (int) $input['floor_id'];
    }
    if (array_key_exists('is_active', $input)) {
        $patch['is_active'] = $input['is_active'];
    }
    if (!$svc->updateDevice($id, $patch)) {
        http_response_code(400);
        echo json_encode(['error' => 'Update failed']);
        exit;
    }
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
