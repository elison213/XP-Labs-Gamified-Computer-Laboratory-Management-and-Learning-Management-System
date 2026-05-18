<?php
/**
 * XPLabs API - POST /api/quizzes/finish-attempt
 * Finish a quiz attempt and calculate the final score.
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

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$attemptId = (int) ($input['attempt_id'] ?? 0);

if (!$attemptId) {
    http_response_code(400);
    echo json_encode(['error' => 'attempt_id is required']);
    exit;
}

try {
    $quizService = new QuizService();
    $result = $quizService->finishAttempt($attemptId);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'percentage' => $result['percentage'],
            'score' => $result['score'],
            'can_view_results' => (bool) ($result['can_view_results'] ?? true),
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => $result['message'] ?? 'Failed to finish attempt']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to finish attempt']);
}
