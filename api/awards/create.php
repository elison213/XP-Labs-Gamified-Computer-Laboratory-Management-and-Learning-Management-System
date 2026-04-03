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

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::requireRole(['admin', 'teacher']);

$data = json_decode(file_get_contents('php://input'), true);
$db = Database::getInstance();
$userId = Auth::id();

$user_id = (int) ($data['user_id'] ?? 0);
$points = (int) ($data['points'] ?? 0);
$reason = trim($data['reason'] ?? '');
$award_type = $data['award_type'] ?? 'other';

if (!$user_id || !$points || !$reason) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
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
    $db->begin();
    
    // Insert award record
    $db->insert('point_awards', [
        'awarded_by' => $userId,
        'user_id' => $user_id,
        'points' => $points,
        'reason' => $reason,
        'award_type' => $award_type
    ]);
    
    // Add points to user_points
    $db->insert('user_points', [
        'user_id' => $user_id,
        'points' => $points,
        'source' => 'award',
        'description' => $reason
    ]);
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => "Awarded {$points} points successfully"]);
} catch (Exception $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}