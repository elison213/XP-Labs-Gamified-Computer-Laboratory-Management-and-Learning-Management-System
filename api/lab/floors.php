<?php
/**
 * XPLabs API - GET/POST /api/lab/floors
 * List or create lab floors.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../services/LabService.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Csrf;
use XPLabs\Services\LabService;

Auth::requireRoles(['admin', 'teacher']);

$labService = new LabService();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $labId = isset($_GET['lab_id']) ? (int) $_GET['lab_id'] : null;
    $floors = $labService->getFloors($labId);
    echo json_encode(['success' => true, 'floors' => $floors]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValidToken();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = trim($input['name'] ?? '');
    if ($name === '') {
        http_response_code(400);
        echo json_encode(['error' => 'name is required']);
        exit;
    }
    if (strlen($name) > 100) {
        http_response_code(400);
        echo json_encode(['error' => 'name is too long']);
        exit;
    }

    $gridCols = (int) ($input['grid_cols'] ?? 6);
    $gridRows = (int) ($input['grid_rows'] ?? 5);
    if ($gridCols < 1 || $gridCols > 20 || $gridRows < 1 || $gridRows > 20) {
        http_response_code(400);
        echo json_encode(['error' => 'grid size must be between 1 and 20']);
        exit;
    }
    $layoutConfig = $input['layout_config'] ?? [];
    if (!is_array($layoutConfig)) {
        http_response_code(400);
        echo json_encode(['error' => 'layout_config must be an object or array']);
        exit;
    }

    $floorId = $labService->createFloor([
        'name' => $name,
        'lab_id' => isset($input['lab_id']) ? (int) $input['lab_id'] : null,
        'building' => trim($input['building'] ?? '') ?: null,
        'floor_number' => (int) ($input['floor_number'] ?? 1),
        'grid_cols' => $gridCols,
        'grid_rows' => $gridRows,
        'layout_config' => $layoutConfig,
    ]);

    echo json_encode(['success' => true, 'floor_id' => $floorId]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
