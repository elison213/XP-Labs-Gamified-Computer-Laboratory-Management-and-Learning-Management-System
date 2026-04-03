<?php
/**
 * XPLabs API - POST /api/submissions/submit
 * Submit an assignment.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/PointService.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Services\PointService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

Auth::requireRole('student');

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if (empty($input['assignment_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'assignment_id is required']);
    exit;
}

$db = Database::getInstance();
$userId = Auth::id();
$assignmentId = (int) $input['assignment_id'];

try {
    // Get assignment details
    $assignment = $db->fetch(
        "SELECT * FROM assignments WHERE id = ? AND is_active = 1",
        [$assignmentId]
    );
    
    if (!$assignment) {
        http_response_code(404);
        echo json_encode(['error' => 'Assignment not found']);
        exit;
    }
    
    // Check if already submitted
    $existing = $db->fetch(
        "SELECT * FROM submissions WHERE assignment_id = ? AND user_id = ?",
        [$assignmentId, $userId]
    );
    
    if ($existing && $existing['status'] === 'graded') {
        http_response_code(400);
        echo json_encode(['error' => 'Assignment already graded, cannot resubmit']);
        exit;
    }
    
    $submissionData = [
        'assignment_id' => $assignmentId,
        'user_id' => $userId,
        'content' => $input['content'] ?? '',
        'file_path' => $input['file_path'] ?? null,
        'status' => 'submitted',
        'submitted_at' => date('Y-m-d H:i:s'),
    ];
    
    // Check if late
    if ($assignment['due_date'] && strtotime('now') > strtotime($assignment['due_date'])) {
        $submissionData['is_late'] = 1;
        if (!$assignment['allow_late']) {
            http_response_code(400);
            echo json_encode(['error' => 'Late submissions not allowed']);
            exit;
        }
    }
    
    if ($existing) {
        // Update existing submission
        $db->update('submissions', $submissionData, 'id = ?', [$existing['id']]);
        $submissionId = $existing['id'];
    } else {
        // Create new submission
        $submissionId = $db->insert('submissions', $submissionData);
    }
    
    // Award submission points
    $pointService = new PointService();
    $config = require __DIR__ . '/../../config/app.php';
    $points = $config['points']['assignment_submit'] ?? 5;
    $pointService->awardPoints($userId, $points, 'assignment_submit', 'submission', $submissionId);
    
    $submission = $db->fetch(
        "SELECT s.*, a.title as assignment_title, a.max_points
         FROM submissions s
         LEFT JOIN assignments a ON s.assignment_id = a.id
         WHERE s.id = ?",
        [$submissionId]
    );
    
    echo json_encode(['success' => true, 'submission' => $submission, 'points_earned' => $points]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}