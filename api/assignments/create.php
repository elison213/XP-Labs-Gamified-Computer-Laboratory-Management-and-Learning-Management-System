<?php
/**
 * XPLabs API - POST /api/assignments/create
 * Create a new assignment.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

Auth::requireRole(['admin', 'teacher']);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if (empty($input['title']) || empty($input['course_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Title and course_id are required']);
    exit;
}

$db = Database::getInstance();
$userId = Auth::id();

try {
    $assignmentId = $db->insert('assignments', [
        'title' => $input['title'],
        'description' => $input['description'] ?? '',
        'course_id' => (int) $input['course_id'],
        'created_by' => $userId,
        'due_date' => $input['due_date'] ?? null,
        'max_points' => (int) ($input['max_points'] ?? 100),
        'allow_late' => !empty($input['allow_late']) ? 1 : 0,
        'late_penalty_percent' => (int) ($input['late_penalty_percent'] ?? 10),
        'is_active' => 1,
    ]);

    $assignment = $db->fetch(
        "SELECT a.*, u.first_name, u.last_name, c.name as course_name
         FROM assignments a
         LEFT JOIN users u ON a.created_by = u.id
         LEFT JOIN courses c ON a.course_id = c.id
         WHERE a.id = ?",
        [$assignmentId]
    );

    echo json_encode(['success' => true, 'assignment' => $assignment]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}