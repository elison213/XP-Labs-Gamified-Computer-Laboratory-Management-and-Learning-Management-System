<?php
/**
 * XPLabs API - GET /api/assignments/list
 * Get assignments for a course or all accessible assignments.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::require();

$db = Database::getInstance();
$role = $_SESSION['user_role'];
$userId = Auth::id();
$courseId = (int) ($_GET['course_id'] ?? 0);

if ($role === 'admin' || $role === 'teacher') {
    if ($courseId) {
        $assignments = $db->fetchAll(
            "SELECT a.*, u.first_name, u.last_name, c.name as course_name,
                    COUNT(DISTINCT s.id) as submission_count,
                    COUNT(DISTINCT CASE WHEN s.status = 'submitted' THEN s.id END) as submitted_count
             FROM assignments a
             LEFT JOIN users u ON a.created_by = u.id
             LEFT JOIN courses c ON a.course_id = c.id
             LEFT JOIN submissions s ON a.id = s.assignment_id
             WHERE a.course_id = ?
             GROUP BY a.id
             ORDER BY a.due_date ASC, a.created_at DESC",
            [$courseId]
        );
    } else {
        $assignments = $db->fetchAll(
            "SELECT a.*, u.first_name, u.last_name, c.name as course_name,
                    COUNT(DISTINCT s.id) as submission_count,
                    COUNT(DISTINCT CASE WHEN s.status = 'submitted' THEN s.id END) as submitted_count
             FROM assignments a
             LEFT JOIN users u ON a.created_by = u.id
             LEFT JOIN courses c ON a.course_id = c.id
             LEFT JOIN submissions s ON a.id = s.assignment_id
             WHERE 1=1
             GROUP BY a.id
             ORDER BY a.due_date ASC, a.created_at DESC"
        );
    }
} else {
    // Student - get assignments for their enrolled courses
    if ($courseId) {
        $assignments = $db->fetchAll(
            "SELECT a.*, u.first_name, u.last_name, c.name as course_name,
                    s.status as my_submission_status, s.submitted_at as my_submitted_at, s.score AS grade
             FROM assignments a
             LEFT JOIN users u ON a.created_by = u.id
             LEFT JOIN courses c ON a.course_id = c.id
             LEFT JOIN submissions s ON a.id = s.assignment_id AND s.user_id = ?
             WHERE a.course_id = ? AND a.status = 'published'
             ORDER BY a.due_date ASC",
            [$userId, $courseId]
        );
    } else {
        $assignments = $db->fetchAll(
            "SELECT a.*, u.first_name, u.last_name, c.name as course_name,
                    s.status as my_submission_status, s.submitted_at as my_submitted_at, s.score AS grade
             FROM assignments a
             LEFT JOIN users u ON a.created_by = u.id
             LEFT JOIN courses c ON a.course_id = c.id
             LEFT JOIN course_enrollments ce ON a.course_id = ce.course_id AND ce.user_id = ?
             LEFT JOIN submissions s ON a.id = s.assignment_id AND s.user_id = ?
             WHERE ce.user_id IS NOT NULL AND a.status = 'published'
             ORDER BY a.due_date ASC",
            [$userId, $userId]
        );
    }
}

echo json_encode(['success' => true, 'assignments' => $assignments]);