<?php
/**
 * XPLabs API - GET /api/users
 * List users with filtering and pagination.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/UserService.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\UserService;

Auth::require();

// Only admin and teachers can list users
Auth::requireRoles(['admin', 'teacher']);

$userService = new UserService();

$filters = [
    'role' => $_GET['role'] ?? null,
    'search' => $_GET['search'] ?? null,
    'is_active' => isset($_GET['is_active']) ? (int) $_GET['is_active'] : null,
];

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 25)));

$result = $userService->list($filters, $page, $perPage);

echo json_encode($result);