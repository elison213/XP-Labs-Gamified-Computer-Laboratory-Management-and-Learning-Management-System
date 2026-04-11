<?php
/**
 * XPLabs API - GET /api/access/folder-rules
 * Get folder access rules for a lab floor and user role.
 * Called by PowerShell AccessControl.ps1 to set NTFS permissions.
 */

require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';
require_once __DIR__ . '/../middleware/MachineAuth.php';

use XPLabs\Services\PCService;
use XPLabs\Api\Middleware\CorsMiddleware;
use XPLabs\Api\Middleware\MachineAuth;

header('Content-Type: application/json');
CorsMiddleware::allowLabPCs();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Authenticate the PC
$pc = MachineAuth::require();

$role = trim($_GET['role'] ?? 'student');

$pcService = new PCService();
$rules = $pcService->getFolderRules($pc['floor_id'], $role);

echo json_encode([
    'success' => true,
    'floor_id' => $pc['floor_id'],
    'role' => $role,
    'rules' => $rules,
    'count' => count($rules),
]);