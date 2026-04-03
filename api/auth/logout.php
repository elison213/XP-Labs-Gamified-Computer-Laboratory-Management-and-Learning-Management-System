<?php
/**
 * XPLabs API - POST /api/auth/logout
 * End user session.
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';

use XPLabs\Lib\Auth;

Auth::getInstance()->logout();

echo json_encode(['success' => true, 'message' => 'Logged out successfully']);