<?php
/**
 * XPLabs API - GET /api/access/drive-maps
 * Get drive mappings for a user role.
 * Called by PowerShell Logon.ps1 to map network drives.
 */

require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';
require_once __DIR__ . '/../middleware/MachineAuth.php';

use XPLabs\Lib\Auth;
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

$role = trim($_GET['role'] ?? 'student');
$username = trim($_GET['username'] ?? '');
$labName = trim($_GET['lab_name'] ?? '');

$pc = MachineAuth::optional();
$isSessionUser = Auth::check();

if (!$pc && !$isSessionUser) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

if ($isSessionUser) {
    Auth::requireRole(['admin', 'teacher']);
}

$allowedRoles = ['student', 'teacher', 'admin'];
if (!in_array($role, $allowedRoles, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid role']);
    exit;
}

$pcService = new PCService();
$mappings = $pcService->getDriveMappings($role);

// Replace variables in network paths
$resolvedMappings = array_map(function ($mapping) use ($username, $labName) {
    $path = $mapping['network_path'];
    $label = $mapping['label'];

    // Replace placeholders
    if ($username) {
        $path = str_replace('%USERNAME%', $username, $path);
        $label = str_replace('%USERNAME%', $username, $label);
    }
    if ($labName) {
        $path = str_replace('%LABNAME%', $labName, $path);
        $label = str_replace('%LABNAME%', $labName, $label);
    }

    return [
        'drive_letter' => $mapping['drive_letter'],
        'network_path' => $path,
        'label' => $label,
        'is_persistent' => (bool) $mapping['is_persistent'],
    ];
}, $mappings);

echo json_encode([
    'success' => true,
    'role' => $role,
    'mappings' => $resolvedMappings,
    'count' => count($resolvedMappings),
]);