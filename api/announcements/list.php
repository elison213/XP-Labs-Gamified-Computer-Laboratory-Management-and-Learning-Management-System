<?php
/**
 * XPLabs API - GET /api/announcements/list
 * Get announcements for the current user.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

Auth::require();

$db = Database::getInstance();
$role = $_SESSION['user_role'];
$userId = Auth::id();

// Admin sees all, teachers see their own + global, students see their course announcements + global
if ($role === 'admin') {
    $announcements = $db->fetchAll(
        "SELECT a.*, u.first_name, u.last_name
         FROM announcements a
         LEFT JOIN users u ON a.created_by = u.id
         WHERE a.is_active = 1
         ORDER BY a.is_pinned DESC, a.created_at DESC"
    );
} elseif ($role === 'teacher') {
    $announcements = $db->fetchAll(
        "SELECT a.*, u.first_name, u.last_name
         FROM announcements a
         LEFT JOIN users u ON a.created_by = u.id
         WHERE a.is_active = 1 AND (a.created_by = ? OR a.target_audience = 'all')
         ORDER BY a.is_pinned DESC, a.created_at DESC",
        [$userId]
    );
} else {
    // Student - get announcements for their courses and global ones
    $announcements = $db->fetchAll(
        "SELECT DISTINCT a.*, u.first_name, u.last_name
         FROM announcements a
         LEFT JOIN users u ON a.created_by = u.id
         LEFT JOIN course_announcements ca ON a.id = ca.announcement_id
         LEFT JOIN course_enrollments ce ON ca.course_id = ce.course_id
         WHERE a.is_active = 1 
           AND (a.target_audience = 'all' OR a.target_audience = 'students' OR ce.user_id = ?)
         ORDER BY a.is_pinned DESC, a.created_at DESC",
        [$userId]
    );
}

echo json_encode(['success' => true, 'announcements' => $announcements]);