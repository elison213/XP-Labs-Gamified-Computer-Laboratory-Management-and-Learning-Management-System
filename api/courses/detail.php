<?php
/**
 * XPLabs API - GET /api/courses/{id}
 * Single course detail (requires course access).
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../middleware/CourseAccessMiddleware.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Middleware\CourseAccessMiddleware;

Auth::require();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$courseId = (int) ($_GET['id'] ?? 0);
if (!$courseId) {
    http_response_code(400);
    echo json_encode(['error' => 'id is required']);
    exit;
}

CourseAccessMiddleware::requireAccess($courseId);

$db = Database::getInstance();
$course = $db->fetch(
    "SELECT c.*, u.first_name as teacher_first, u.last_name as teacher_last, u.email as teacher_email
     FROM courses c
     LEFT JOIN users u ON c.teacher_id = u.id
     WHERE c.id = ?",
    [$courseId]
);

if (!$course) {
    http_response_code(404);
    echo json_encode(['error' => 'Course not found']);
    exit;
}

echo json_encode(['success' => true, 'course' => $course]);
