<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../lib/Auth.php';
require_once __DIR__ . '/../../../lib/Csrf.php';
require_once __DIR__ . '/../../../services/QuizService.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Csrf;
use XPLabs\Services\QuizService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

Auth::requireRole('student');
Csrf::requireValidToken();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$attemptId = (int) ($input['attempt_id'] ?? 0);
$powerupId = (int) ($input['powerup_id'] ?? 0);
if ($attemptId <= 0 || $powerupId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'attempt_id and powerup_id are required']);
    exit;
}

$service = new QuizService();
$result = $service->purchaseOrRedeemPowerup($attemptId, $powerupId, Auth::id());
if (!empty($result['success'])) {
    echo json_encode($result);
    exit;
}
http_response_code(400);
echo json_encode(['error' => $result['message'] ?? 'Unable to redeem powerup']);
