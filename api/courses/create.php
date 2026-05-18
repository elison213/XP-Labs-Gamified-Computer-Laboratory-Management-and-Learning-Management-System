<?php
/**
 * XPLabs API - POST /api/courses
 * Create a course (admin only).
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::require();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$code = trim($input['code'] ?? '');
$name = trim($input['name'] ?? '');
$subject = trim($input['subject'] ?? 'other');
$teacherId = (int) ($input['teacher_id'] ?? 0);
$description = trim($input['description'] ?? '');
$targetGrade = trim($input['target_grade'] ?? '');
$targetSection = trim($input['target_section'] ?? '');
$academicYear = trim($input['academic_year'] ?? '');
$semester = trim($input['semester'] ?? '');

if ($code === '' || $name === '' || $teacherId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'code, name, and teacher_id are required']);
    exit;
}

$db = Database::getInstance();
$teacher = $db->fetch("SELECT id FROM users WHERE id = ? AND role = 'teacher' AND is_active = 1", [$teacherId]);
if (!$teacher) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid teacher_id']);
    exit;
}

$allowedSubjects = ['computer_programming', 'web_development', 'visual_graphics', 'it_fundamentals', 'cs_concepts', 'other'];
if (!in_array($subject, $allowedSubjects, true)) {
    $subject = 'other';
}

$courseId = $db->insert('courses', [
    'code' => $code,
    'name' => $name,
    'subject' => $subject,
    'description' => $description !== '' ? $description : null,
    'teacher_id' => $teacherId,
    'target_grade' => $targetGrade !== '' ? $targetGrade : null,
    'target_section' => $targetSection !== '' ? $targetSection : null,
    'academic_year' => $academicYear !== '' ? $academicYear : null,
    'semester' => $semester !== '' ? $semester : null,
    'status' => 'active',
]);

echo json_encode(['success' => true, 'course_id' => $courseId]);
