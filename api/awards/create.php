<?php
/**
 * XPLabs API - POST /api/awards/create
 * Award points to a student
 */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../services/PointService.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Lib\Csrf;
use XPLabs\Services\PointService;

Auth::requireRole(['admin', 'teacher']);
Csrf::requireValidToken();

$data = json_decode(file_get_contents('php://input'), true);
$db = Database::getInstance();
$userId = Auth::id();
$pointService = new PointService();

$user_id = (int) ($data['user_id'] ?? 0);
$points = (int) ($data['points'] ?? 0);
$reason = trim($data['reason'] ?? '');
$award_type = $data['award_type'] ?? 'other';
$mode = strtolower(trim($data['mode'] ?? 'award')); // 'award' | 'deduct'

if (!$user_id || !$points || !$reason) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

if (!in_array($mode, ['award', 'deduct'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid mode']);
    exit;
}

// Verify student exists
$student = $db->fetchOne("SELECT id FROM users WHERE id = ? AND role = 'student'", [$user_id]);
if (!$student) {
    http_response_code(404);
    echo json_encode(['error' => 'Student not found']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Insert award record (store negative points for deductions)
    $db->insert('point_awards', [
        'awarded_by' => $userId,
        'user_id' => $user_id,
        'points' => $mode === 'deduct' ? -abs($points) : abs($points),
        'reason' => $reason,
        'award_type' => $award_type
    ]);
    
    if ($mode === 'deduct') {
        $ok = $pointService->deductPoints($user_id, abs($points), 'deduct', 'point_award', null, $userId);
        if (!$ok) {
            $db->rollback();
            http_response_code(409);
            echo json_encode(['error' => 'Insufficient points to deduct']);
            exit;
        }
    } else {
        // Add points to user_points
        $pointService->awardPoints($user_id, abs($points), 'award', 'point_award', null, $userId);
    }
    
    $db->commit();
    
    $msgVerb = $mode === 'deduct' ? 'Deducted' : 'Awarded';
    echo json_encode(['success' => true, 'message' => "{$msgVerb} " . abs($points) . " points successfully"]);
} catch (\Throwable $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}