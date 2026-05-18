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
$targetSection = trim($input['target_section'] ?? '');
$academicYear = trim($input['academic_year'] ?? '');
$gradingTrack = trim($input['grading_track'] ?? 'semester');
$gradingPeriodIndex = isset($input['grading_period_index']) ? (int) $input['grading_period_index'] : 0;

if ($code === '' || $name === '' || $teacherId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'code, name, and teacher_id are required']);
    exit;
}

if (!in_array($gradingTrack, ['quarter', 'semester'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'grading_track must be quarter or semester']);
    exit;
}

if ($gradingTrack === 'quarter') {
    if ($gradingPeriodIndex < 1 || $gradingPeriodIndex > 4) {
        http_response_code(400);
        echo json_encode(['error' => 'grading_period_index must be 1–4 for quarterly (JHS)']);
        exit;
    }
} elseif ($gradingPeriodIndex < 1 || $gradingPeriodIndex > 2) {
    http_response_code(400);
    echo json_encode(['error' => 'grading_period_index must be 1–2 for semester (SHS)']);
    exit;
}

$semesterLabel = '';
if ($gradingTrack === 'quarter' && $gradingPeriodIndex >= 1 && $gradingPeriodIndex <= 4) {
    $ords = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'];
    $semesterLabel = $ords[$gradingPeriodIndex] . ' Quarter';
} elseif ($gradingTrack === 'semester' && $gradingPeriodIndex >= 1 && $gradingPeriodIndex <= 2) {
    $semesterLabel = $gradingPeriodIndex === 1 ? '1st Semester' : '2nd Semester';
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

$insert = [
    'code' => $code,
    'name' => $name,
    'subject' => $subject,
    'description' => $description !== '' ? $description : null,
    'teacher_id' => $teacherId,
    'target_grade' => null,
    'target_section' => $targetSection !== '' ? $targetSection : null,
    'academic_year' => $academicYear !== '' ? $academicYear : null,
    'semester' => $semesterLabel !== '' ? $semesterLabel : null,
    'status' => 'active',
];

$hasGrading = (int) $db->fetchOne(
    "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'courses' AND column_name = 'grading_track'"
) > 0;
if ($hasGrading) {
    $insert['grading_track'] = $gradingTrack;
    $insert['grading_period_index'] = $gradingPeriodIndex;
}

$courseId = $db->insert('courses', $insert);

echo json_encode(['success' => true, 'course_id' => $courseId]);
