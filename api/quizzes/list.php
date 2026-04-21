<?php
/**
 * XPLabs API - GET /api/quizzes
 * List quizzes (optionally filtered by course_id).
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../services/QuizService.php';
require_once __DIR__ . '/../../middleware/CourseAccessMiddleware.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\QuizService;
use XPLabs\Middleware\CourseAccessMiddleware;

Auth::require();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$courseId = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
$role = $_SESSION['user_role'] ?? '';
$userId = Auth::id();

$quizService = new QuizService();
$db = \XPLabs\Lib\Database::getInstance();

if ($courseId > 0) {
    CourseAccessMiddleware::requireAccess($courseId);
    $quizzes = $quizService->getCourseQuizzes($courseId);
} elseif ($role === 'admin') {
    $quizzes = $db->fetchAll(
        "SELECT q.*, c.name as course_name, u.first_name, u.last_name
         FROM quizzes q
         LEFT JOIN courses c ON q.course_id = c.id
         LEFT JOIN users u ON q.created_by = u.id
         ORDER BY q.created_at DESC
         LIMIT 500"
    );
} elseif ($role === 'teacher') {
    $quizzes = $db->fetchAll(
        "SELECT q.*, c.name as course_name, u.first_name, u.last_name
         FROM quizzes q
         JOIN courses c ON q.course_id = c.id
         LEFT JOIN users u ON q.created_by = u.id
         WHERE c.teacher_id = ?
         ORDER BY q.created_at DESC
         LIMIT 500",
        [$userId]
    );
} else {
    http_response_code(400);
    echo json_encode(['error' => 'course_id is required']);
    exit;
}

echo json_encode(['success' => true, 'quizzes' => $quizzes]);
