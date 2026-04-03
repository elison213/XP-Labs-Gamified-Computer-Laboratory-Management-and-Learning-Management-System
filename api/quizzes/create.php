<?php
/**
 * XPLabs API - POST /api/quizzes/create
 * Create a new quiz with questions.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/QuizService.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\QuizService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

Auth::requireRole(['admin', 'teacher']);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$required = ['title', 'course_id'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Field '$field' is required"]);
        exit;
    }
}

$quizService = new QuizService();

try {
    $quizId = $quizService->createQuiz([
        'title' => $input['title'],
        'description' => $input['description'] ?? null,
        'course_id' => (int) $input['course_id'],
        'quiz_code' => $input['quiz_code'] ?? null,
        'time_limit_seconds' => $input['time_limit_seconds'] ?? null,
        'start_time' => $input['start_time'] ?? null,
        'end_time' => $input['end_time'] ?? null,
        'max_attempts' => (int) ($input['max_attempts'] ?? 1),
        'shuffle_questions' => !empty($input['shuffle_questions']) ? 1 : 0,
        'show_results_immediately' => !empty($input['show_results_immediately']) ? 1 : 0,
        'created_by' => Auth::id(),
    ]);

    // Add questions if provided
    if (!empty($input['questions']) && is_array($input['questions'])) {
        $orderNum = 1;
        foreach ($input['questions'] as $q) {
            $quizService->addQuestion($quizId, [
                'question_text' => $q['question_text'],
                'question_type' => $q['question_type'] ?? 'multiple_choice',
                'options' => $q['options'] ?? null,
                'correct_answer' => $q['correct_answer'] ?? null,
                'points' => $q['points'] ?? 10,
                'order_num' => $orderNum++,
            ]);
        }
    }

    // Publish if requested
    if (!empty($input['publish'])) {
        $quizService->publishQuiz($quizId);
    }

    $quiz = $quizService->getQuiz($quizId);
    echo json_encode(['success' => true, 'quiz' => $quiz]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}