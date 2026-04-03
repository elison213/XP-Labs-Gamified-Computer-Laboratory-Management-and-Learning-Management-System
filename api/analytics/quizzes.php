<?php
/**
 * XPLabs API - GET /api/analytics/quizzes
 * Quiz performance analytics
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::requireRole(['admin', 'teacher']);

$db = Database::getInstance();
$userId = Auth::id();
$courseId = (int) ($_GET['course_id'] ?? 0);

// Build query based on role
$where = "q.status = 'published'";
$params = [];

if ($courseId) {
    $where .= " AND q.course_id = ?";
    $params[] = $courseId;
} else {
    // For teachers, only their courses
    $role = $_SESSION['user_role'];
    if ($role === 'teacher') {
        $where .= " AND q.course_id IN (SELECT id FROM courses WHERE teacher_id = ?)";
        $params[] = $userId;
    }
}

// Get quiz averages
$quizzes = $db->fetchAll(
    "SELECT q.id, q.title, 
            COALESCE(AVG(qa.score_percent), 0) as avg_score,
            COUNT(qa.id) as attempts
     FROM quizzes q
     LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.status = 'completed'
     WHERE $where
     GROUP BY q.id
     ORDER BY q.created_at DESC
     LIMIT 20",
    $params
);

// Score distribution
$dist = $db->fetchOne(
    "SELECT 
        SUM(CASE WHEN score_percent >= 90 THEN 1 ELSE 0 END) as excellent,
        SUM(CASE WHEN score_percent >= 75 AND score_percent < 90 THEN 1 ELSE 0 END) as good,
        SUM(CASE WHEN score_percent >= 60 AND score_percent < 75 THEN 1 ELSE 0 END) as fair,
        SUM(CASE WHEN score_percent < 60 THEN 1 ELSE 0 END) as poor
     FROM quiz_attempts qa
     JOIN quizzes q ON qa.quiz_id = q.id
     WHERE qa.status = 'completed' AND $where",
    $params
);

// Overall average
$avgScore = $db->fetchOne(
    "SELECT COALESCE(AVG(score_percent), 0) as avg
     FROM quiz_attempts qa
     JOIN quizzes q ON qa.quiz_id = q.id
     WHERE qa.status = 'completed' AND $where",
    $params
);

echo json_encode([
    'success' => true,
    'quizzes' => $quizzes,
    'distribution' => [
        'excellent' => (int) ($dist['excellent'] ?? 0),
        'good' => (int) ($dist['good'] ?? 0),
        'fair' => (int) ($dist['fair'] ?? 0),
        'poor' => (int) ($dist['poor'] ?? 0)
    ],
    'avg_score' => round($avgScore['avg'] ?? 0, 1)
]);