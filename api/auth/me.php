<?php
/**
 * XPLabs API - GET /api/auth/me
 * Get current authenticated user info.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';

use XPLabs\Lib\Auth;

Auth::require();

$user = Auth::getInstance()->user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Remove sensitive fields
unset($user['password_hash']);

echo json_encode(['user' => $user]);