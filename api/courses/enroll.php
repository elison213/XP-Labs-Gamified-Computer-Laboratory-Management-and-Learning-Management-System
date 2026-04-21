<?php
/**
 * XPLabs API - POST /api/courses/{id}/enroll
 * Enroll a user in a course (admin or course teacher).
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::require();
Auth::requireRoles(['admin', 'teacher']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$courseId = (int) ($input['course_id'] ?? $input['id'] ?? 0);
$userId = (int) ($input['user_id'] ?? 0);

if (!$courseId || !$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'course_id and user_id are required']);
    exit;
}

$db = Database::getInstance();
$course = $db->fetch("SELECT * FROM courses WHERE id = ?", [$courseId]);
if (!$course) {
    http_response_code(404);
    echo json_encode(['error' => 'Course not found']);
    exit;
}

$actorId = Auth::id();
$role = $_SESSION['user_role'] ?? '';
if ($role === 'teacher' && (int) $course['teacher_id'] !== $actorId) {
    http_response_code(403);
    echo json_encode(['error' => 'Only this course teacher can enroll students']);
    exit;
}

$target = $db->fetch("SELECT id, role FROM users WHERE id = ? AND is_active = 1", [$userId]);
if (!$target || $target['role'] !== 'student') {
    http_response_code(400);
    echo json_encode(['error' => 'user_id must be an active student']);
    exit;
}

$existing = $db->fetch(
    "SELECT id, status FROM course_enrollments WHERE course_id = ? AND user_id = ?",
    [$courseId, $userId]
);

if ($existing) {
    if ($existing['status'] !== 'enrolled') {
        $db->update('course_enrollments', ['status' => 'enrolled', 'enrolled_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $existing['id']]);
    }
    echo json_encode(['success' => true, 'message' => 'Already enrolled', 'enrollment_id' => (int) $existing['id']]);
    exit;
}

$enrollmentId = $db->insert('course_enrollments', [
    'course_id' => $courseId,
    'user_id' => $userId,
    'status' => 'enrolled',
    'enrolled_at' => date('Y-m-d H:i:s'),
]);

echo json_encode(['success' => true, 'enrollment_id' => $enrollmentId]);
