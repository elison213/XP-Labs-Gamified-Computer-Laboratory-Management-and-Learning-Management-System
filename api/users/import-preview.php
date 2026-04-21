<?php
/**
 * XPLabs API - POST /api/users/import-preview
 * Preview CSV import without writing to the database.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../services/UserService.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Csrf;
use XPLabs\Services\UserService;

Auth::require();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

Csrf::requireValidToken();

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['rows']) || empty($input['column_mapping'])) {
    http_response_code(400);
    echo json_encode(['error' => 'rows and column_mapping are required']);
    exit;
}

$userService = new UserService();

try {
    $preview = $userService->previewImportFromCsv(
        $input['rows'],
        $input['column_mapping']
    );

    echo json_encode([
        'success' => true,
        'preview' => $preview,
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
