<?php
/**
 * XPLabs API - GET /api/analytics/feedback
 * Activity feedback analytics (fun rating, difficulty)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::requireRole(['admin', 'teacher']);

$db = Database::getInstance();
$userId = Auth::id();
$role = Auth::role();
$courseId = (int) ($_GET['course_id'] ?? 0);

// Build where clause
$where = "1=1";
$params = [];

if ($courseId) {
    if ($role === 'teacher') {
        $owned = (int) $db->fetchOne("SELECT COUNT(*) FROM courses WHERE id = ? AND teacher_id = ?", [$courseId, $userId]);
        if ($owned === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid course access']);
            exit;
        }
    }
    $where .= " AND (
        (af.activity_type = 'quiz' AND af.activity_id IN (SELECT id FROM quizzes WHERE course_id = ?))
        OR
        (af.activity_type = 'assignment' AND af.activity_id IN (SELECT id FROM assignments WHERE course_id = ?))
        OR
        (af.activity_type = 'lab_session')
    )";
    $params[] = $courseId;
    $params[] = $courseId;
} elseif ($role === 'teacher') {
    $where .= " AND (
        (af.activity_type = 'quiz' AND af.activity_id IN (SELECT id FROM quizzes WHERE course_id IN (SELECT id FROM courses WHERE teacher_id = ?)))
        OR
        (af.activity_type = 'assignment' AND af.activity_id IN (SELECT id FROM assignments WHERE course_id IN (SELECT id FROM courses WHERE teacher_id = ?)))
        OR
        (af.activity_type = 'lab_session')
    )";
    $params[] = $userId;
    $params[] = $userId;
}

// Fun rating distribution (1-5 stars)
$funDist = $db->fetch(
    "SELECT 
        SUM(CASE WHEN fun_rating = 1 THEN 1 ELSE 0 END) as s1,
        SUM(CASE WHEN fun_rating = 2 THEN 1 ELSE 0 END) as s2,
        SUM(CASE WHEN fun_rating = 3 THEN 1 ELSE 0 END) as s3,
        SUM(CASE WHEN fun_rating = 4 THEN 1 ELSE 0 END) as s4,
        SUM(CASE WHEN fun_rating = 5 THEN 1 ELSE 0 END) as s5,
        AVG(fun_rating) as avg_fun
     FROM activity_feedback af
     WHERE $where",
    $params
);

// Difficulty breakdown
$diff = $db->fetch(
    "SELECT 
        SUM(CASE WHEN difficulty = 'easy' THEN 1 ELSE 0 END) as easy,
        SUM(CASE WHEN difficulty = 'medium' THEN 1 ELSE 0 END) as medium,
        SUM(CASE WHEN difficulty = 'hard' THEN 1 ELSE 0 END) as hard
     FROM activity_feedback af
     WHERE $where",
    $params
);

echo json_encode([
    'success' => true,
    'fun_distribution' => [
        (int) ($funDist['s1'] ?? 0),
        (int) ($funDist['s2'] ?? 0),
        (int) ($funDist['s3'] ?? 0),
        (int) ($funDist['s4'] ?? 0),
        (int) ($funDist['s5'] ?? 0)
    ],
    'avg_fun' => round($funDist['avg_fun'] ?? 0, 1),
    'difficulty' => [
        'easy' => (int) ($diff['easy'] ?? 0),
        'medium' => (int) ($diff['medium'] ?? 0),
        'hard' => (int) ($diff['hard'] ?? 0)
    ]
]);