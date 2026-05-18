<?php
/**
 * XPLabs API - POST /api/quizzes/create
 * Create a new quiz with questions.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../services/QuizService.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Csrf;
use XPLabs\Services\QuizService;

// #region agent log
$__debugLog = static function (string $runId, string $hypothesisId, string $location, string $message, array $data = []): void {
    file_put_contents(__DIR__ . '/../../debug-10ea95.log', json_encode(['sessionId' => '10ea95', 'runId' => $runId, 'hypothesisId' => $hypothesisId, 'location' => $location, 'message' => $message, 'data' => $data, 'timestamp' => (int) round(microtime(true) * 1000)], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
};
// #endregion

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

Auth::requireRole(['admin', 'teacher']);
Csrf::requireValidToken();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// #region agent log
$__debugLog('initial', 'H2', 'api/quizzes/create.php:29', 'quiz_create_request_received', ['has_title' => !empty($input['title']), 'course_id' => isset($input['course_id']) ? (int) $input['course_id'] : null, 'question_count' => is_array($input['questions'] ?? null) ? count($input['questions']) : 0, 'publish' => !empty($input['publish'])]);
// #endregion

$required = ['title', 'course_id'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Field '$field' is required"]);
        exit;
    }
}
try {
    $quizService = new QuizService();
    $quizId = $quizService->createQuiz([
        'title' => $input['title'],
        'description' => $input['description'] ?? null,
        'course_id' => (int) $input['course_id'],
        'time_limit_per_q' => (int) ($input['time_limit_per_q'] ?? 30),
        'max_attempts' => (int) ($input['max_attempts'] ?? 1),
        'scheduled_at' => $input['scheduled_at'] ?? null,
        'closes_at' => $input['closes_at'] ?? null,
        'shuffle_questions' => !empty($input['shuffle_questions']) ? 1 : 0,
        'shuffle_answers' => !empty($input['shuffle_answers']) ? 1 : 0,
        'show_live_leaderboard' => !empty($input['show_live_leaderboard']) ? 1 : 0,
        'allow_powerups' => !empty($input['allow_powerups']) ? 1 : 0,
        'show_results_immediately' => array_key_exists('show_results_immediately', $input) ? (!empty($input['show_results_immediately']) ? 1 : 0) : 1,
        'created_by' => Auth::id(),
    ]);

    // Add questions if provided
    if (!empty($input['questions']) && is_array($input['questions'])) {
        $questionNumber = 1;
        foreach ($input['questions'] as $q) {
            $quizService->addQuestion($quizId, [
                'question_text' => $q['question_text'],
                'type' => $q['type'] ?? ($q['question_type'] ?? 'multiple_choice'),
                'options' => $q['options'] ?? null,
                'correct_answer' => $q['correct_answer'] ?? null,
                'points' => $q['points'] ?? 10,
                'question_number' => $questionNumber++,
            ]);
        }
    }

    // Publish if requested
    if (!empty($input['publish'])) {
        $quizService->publishQuiz($quizId);
    }

    $quiz = $quizService->getQuiz($quizId);
    // #region agent log
    $__debugLog('initial', 'H2', 'api/quizzes/create.php:79', 'quiz_create_success', ['quiz_id' => $quizId, 'question_count' => is_array($input['questions'] ?? null) ? count($input['questions']) : 0, 'published' => !empty($input['publish'])]);
    // #endregion
    echo json_encode(['success' => true, 'quiz' => $quiz]);
} catch (\Throwable $e) {
    http_response_code(400);
    // #region agent log
    $__debugLog('initial', 'H2', 'api/quizzes/create.php:84', 'quiz_create_exception', ['type' => get_class($e), 'message' => $e->getMessage()]);
    // #endregion
    echo json_encode(['error' => $e->getMessage()]);
}