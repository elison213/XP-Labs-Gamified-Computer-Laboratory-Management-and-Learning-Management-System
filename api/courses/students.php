<?php
/**
 * XPLabs API - GET /api/courses/{id}/students
 * List enrolled students for a course.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../middleware/CourseAccessMiddleware.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Middleware\CourseAccessMiddleware;

Auth::require();
Auth::requireRoles(['admin', 'teacher']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$courseId = (int) ($_GET['id'] ?? $_GET['course_id'] ?? 0);
if (!$courseId) {
    http_response_code(400);
    echo json_encode(['error' => 'course id is required']);
    exit;
}

CourseAccessMiddleware::requireAccess($courseId);

$db = Database::getInstance();
$students = $db->fetchAll(
    "SELECT u.id, u.lrn, u.first_name, u.last_name, u.email, ce.status, ce.enrolled_at
     FROM course_enrollments ce
     JOIN users u ON ce.user_id = u.id
     WHERE ce.course_id = ? AND ce.status = 'enrolled'
     ORDER BY u.last_name, u.first_name",
    [$courseId]
);

echo json_encode(['success' => true, 'students' => $students]);
