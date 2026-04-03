<?php
/**
 * XPLabs API - POST /api/quizzes/submit-answer
 * Submit an answer for a quiz question.
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

Auth::require();

$input = json_decode(file_get_contents('php://input'), true);
$attemptId = (int) ($input['attempt_id'] ?? 0);
$questionId = (int) ($input['question_id'] ?? 0);
$answer = $input['answer'] ?? null;
$powerupId = !empty($input['powerup_id']) ? (int) $input['powerup_id'] : null;

if (!$attemptId || !$questionId || $answer === null) {
    http_response_code(400);
    echo json_encode(['error' => 'attempt_id, question_id, and answer are required']);
    exit;
}

$quizService = new QuizService();
$result = $quizService->submitAnswer($attemptId, $questionId, $answer, $powerupId);

if ($result['success']) {
    echo json_encode($result);
} else {
    http_response_code(400);
    echo json_encode(['error' => $result['message']]);
}