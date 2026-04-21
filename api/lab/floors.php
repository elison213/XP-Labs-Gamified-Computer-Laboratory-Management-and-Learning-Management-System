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

    $floorId = $labService->createFloor([
        'name' => $name,
        'lab_id' => isset($input['lab_id']) ? (int) $input['lab_id'] : null,
        'building' => trim($input['building'] ?? '') ?: null,
        'floor_number' => (int) ($input['floor_number'] ?? 1),
        'grid_cols' => (int) ($input['grid_cols'] ?? 6),
        'grid_rows' => (int) ($input['grid_rows'] ?? 5),
        'layout_config' => $input['layout_config'] ?? [],
    ]);

    echo json_encode(['success' => true, 'floor_id' => $floorId]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
