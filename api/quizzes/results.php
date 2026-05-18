<?php
/**
 * XPLabs API - GET /api/quizzes/{id}/results
 * - attempt_id: detailed results for one attempt (student owns, or teacher/admin)
 * - quiz_id: list completed attempts for that quiz (teacher/admin)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/QuizService.php';
require_once __DIR__ . '/../../middleware/CourseAccessMiddleware.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Services\QuizService;
use XPLabs\Middleware\CourseAccessMiddleware;

Auth::require();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$attemptId = (int) ($_GET['attempt_id'] ?? 0);
$quizId = (int) ($_GET['id'] ?? $_GET['quiz_id'] ?? 0);

$quizService = new QuizService();
$db = Database::getInstance();
$userId = Auth::id();
$role = $_SESSION['user_role'] ?? '';

if ($attemptId > 0) {
    $attempt = $db->fetch("SELECT * FROM quiz_attempts WHERE id = ?", [$attemptId]);
    if (!$attempt) {
        http_response_code(404);
        echo json_encode(['error' => 'Attempt not found']);
        exit;
    }
    $quiz = $quizService->getQuiz((int) $attempt['quiz_id']);
    if (!$quiz) {
        http_response_code(404);
        echo json_encode(['error' => 'Quiz not found']);
        exit;
    }

    $isOwner = (int) $attempt['user_id'] === $userId;
    $isStaff = in_array($role, ['admin', 'teacher'], true);
    if (!$isOwner && !$isStaff) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    if ($isStaff) {
        CourseAccessMiddleware::requireAccess((int) $quiz['course_id']);
    }

    $results = $quizService->getResults($attemptId);
    if (!$results) {
        http_response_code(404);
        echo json_encode(['error' => 'Results not available']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $results]);
    exit;
}

if ($quizId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Provide attempt_id or quiz_id']);
    exit;
}

if (!in_array($role, ['admin', 'teacher'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Teacher or admin required']);
    exit;
}

$quiz = $quizService->getQuiz($quizId);
if (!$quiz) {
    http_response_code(404);
    echo json_encode(['error' => 'Quiz not found']);
    exit;
}

CourseAccessMiddleware::requireAccess((int) $quiz['course_id']);

$attempts = $db->fetchAll(
    "SELECT qa.*, u.lrn, u.first_name, u.last_name,
            CASE WHEN qa.max_score > 0 THEN ROUND((qa.total_score / qa.max_score) * 100, 2) ELSE 0 END AS score_percentage
     FROM quiz_attempts qa
     JOIN users u ON qa.user_id = u.id
     WHERE qa.quiz_id = ? AND qa.status = 'completed'
     ORDER BY score_percentage DESC, qa.finished_at DESC",
    [$quizId]
);

echo json_encode(['success' => true, 'quiz' => $quiz, 'attempts' => $attempts]);
