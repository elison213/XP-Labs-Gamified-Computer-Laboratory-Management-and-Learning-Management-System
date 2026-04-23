<?php
/**
 * XPLabs API - GET/POST /api/lab/floors/{id}/layout
 * Read or save floor layout (stations + grid).
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../services/LabService.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Csrf;
use XPLabs\Services\LabService;

Auth::requireRoles(['admin', 'teacher']);

$floorId = (int) ($_GET['floor_id'] ?? $_GET['id'] ?? 0);
if (!$floorId) {
    $path = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/floors/(\d+)/layout#', $path, $m)) {
        $floorId = (int) $m[1];
    }
}

if (!$floorId) {
    http_response_code(400);
    echo json_encode(['error' => 'floor_id is required']);
    exit;
}

$labService = new LabService();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $layout = $labService->getFloorLayout($floorId);
    echo json_encode(['success' => true, 'data' => $layout]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValidToken();
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON body required']);
        exit;
    }
    $stations = $input['stations'] ?? null;
    if (is_array($stations) && count($stations) > 500) {
        http_response_code(400);
        echo json_encode(['error' => 'Too many stations in payload']);
        exit;
    }
    $grid = $input['grid'] ?? null;
    if (is_array($grid)) {
        $rows = (int) ($grid['rows'] ?? 0);
        $cols = (int) ($grid['cols'] ?? 0);
        if (($rows > 0 && $rows > 30) || ($cols > 0 && $cols > 30)) {
            http_response_code(400);
            echo json_encode(['error' => 'Grid dimensions exceed maximum limits']);
            exit;
        }
    }

    $ok = $labService->saveFloorLayout($floorId, $input);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save layout']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
