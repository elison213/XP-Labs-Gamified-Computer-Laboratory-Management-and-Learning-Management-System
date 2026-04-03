<?php
/**
 * XPLabs API - POST /api/auth/login
 * Authenticate user and start session.
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

$input = json_decode(file_get_contents('php://input'), true);
$lrn = trim($input['lrn'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($lrn) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'LRN and password are required']);
    exit;
}

$auth = Auth::getInstance();
$db = \XPLabs\Lib\Database::getInstance();

$user = $db->fetch("SELECT * FROM users WHERE lrn = ? AND is_active = 1", [$lrn]);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

if (!password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

if ($auth->login($user['id'], $user['role'])) {
    $auth->regenerateSession();

    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $basePath = preg_replace('#/api/auth$#', '', $basePath);
    
    // Build absolute URL with protocol and host
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $protocol . '://' . $host . $basePath;

    $redirectPath = match ($user['role']) {
        'admin' => '/dashboard_admin.php',
        'teacher' => '/dashboard_teacher.php',
        'student' => '/dashboard_student.php',
        default => '/index.php',
    };

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'lrn' => $user['lrn'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role' => $user['role'],
        ],
        'redirect' => $baseUrl . $redirectPath,
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Login failed']);
}