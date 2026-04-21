<?php
/**
 * XPLabs API - GET /api/quizzes/{id}/questions
 * List questions for a quiz (correct_answer hidden for students).
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

$quizId = (int) ($_GET['id'] ?? $_GET['quiz_id'] ?? 0);
if (!$quizId) {
    http_response_code(400);
    echo json_encode(['error' => 'quiz_id is required']);
    exit;
}

$quizService = new QuizService();
$quiz = $quizService->getQuiz($quizId);
if (!$quiz) {
    http_response_code(404);
    echo json_encode(['error' => 'Quiz not found']);
    exit;
}

CourseAccessMiddleware::requireAccess((int) $quiz['course_id']);

$questions = $quizService->getQuestions($quizId);
$role = $_SESSION['user_role'] ?? '';
$canSeeAnswers = in_array($role, ['admin', 'teacher'], true);

if (!$canSeeAnswers) {
    $questions = array_map(function ($q) {
        unset($q['correct_answer']);
        return $q;
    }, $questions);
}

echo json_encode(['success' => true, 'quiz' => $quiz, 'questions' => $questions]);
