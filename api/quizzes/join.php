<?php
/**
 * XPLabs API - POST /api/quizzes/{id}/join
 * Join a quiz by quiz ID.
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
$quizId = (int) ($input['quiz_id'] ?? ($_GET['id'] ?? 0));

if (!$quizId) {
    http_response_code(400);
    echo json_encode(['error' => 'quiz_id is required']);
    exit;
}

$quizService = new QuizService();

$userId = Auth::id();
$result = $quizService->startAttempt($quizId, $userId);

if ($result['success']) {
    echo json_encode($result);
} else {
    http_response_code(400);
    echo json_encode(['error' => $result['message']]);
}