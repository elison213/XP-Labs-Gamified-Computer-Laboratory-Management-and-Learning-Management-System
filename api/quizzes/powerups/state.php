<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../lib/Auth.php';
require_once __DIR__ . '/../../../services/QuizService.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\QuizService;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

Auth::requireRole('student');
$attemptId = (int) ($_GET['attempt_id'] ?? 0);
if ($attemptId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'attempt_id is required']);
    exit;
}

$service = new QuizService();
$result = $service->getAttemptPowerupState($attemptId, Auth::id());
if (!empty($result['success'])) {
    echo json_encode($result);
    exit;
}
http_response_code(400);
echo json_encode(['error' => $result['message'] ?? 'Unable to fetch powerup state']);
