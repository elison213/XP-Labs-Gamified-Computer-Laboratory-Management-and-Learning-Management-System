<?php
/**
 * XPLabs API - POST /api/announcements/create
 * Create a new announcement.
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

if (empty($input['title'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Title is required']);
    exit;
}

$db = Database::getInstance();
$userId = Auth::id();
$role = $_SESSION['user_role'];

try {
    $announcementId = $db->insert('announcements', [
        'title' => $input['title'],
        'content' => $input['content'] ?? '',
        'created_by' => $userId,
        'target_audience' => $input['target_audience'] ?? 'all',
        'is_pinned' => !empty($input['is_pinned']) ? 1 : 0,
        'is_active' => 1,
        'expires_at' => $input['expires_at'] ?? null,
    ]);

    // Link to courses if specified
    if (!empty($input['course_ids']) && is_array($input['course_ids'])) {
        foreach ($input['course_ids'] as $courseId) {
            $db->insert('course_announcements', [
                'announcement_id' => $announcementId,
                'course_id' => (int) $courseId,
            ]);
        }
    }

    $announcement = $db->fetch(
        "SELECT a.*, u.first_name, u.last_name
         FROM announcements a
         LEFT JOIN users u ON a.created_by = u.id
         WHERE a.id = ?",
        [$announcementId]
    );

    echo json_encode(['success' => true, 'announcement' => $announcement]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}