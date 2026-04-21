<?php
/**
 * XPLabs API - GET /api/courses
 * List courses visible to the current user.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::require();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db = Database::getInstance();
$userId = Auth::id();
$role = $_SESSION['user_role'] ?? '';

$status = $_GET['status'] ?? null;
$params = [];
$where = ['1=1'];

if ($status !== null && $status !== '') {
    $where[] = 'c.status = ?';
    $params[] = $status;
}

if ($role === 'teacher') {
    $where[] = 'c.teacher_id = ?';
    $params[] = $userId;
} elseif ($role === 'student') {
    $sql = "SELECT c.*, u.first_name as teacher_first, u.last_name as teacher_last
            FROM courses c
            JOIN course_enrollments ce ON ce.course_id = c.id AND ce.user_id = ? AND ce.status = 'enrolled'
            LEFT JOIN users u ON c.teacher_id = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.name ASC";
    array_unshift($params, $userId);
    $courses = $db->fetchAll($sql, $params);
    echo json_encode(['success' => true, 'courses' => $courses]);
    exit;
}

$sql = "SELECT c.*, u.first_name as teacher_first, u.last_name as teacher_last
        FROM courses c
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.name ASC";

$courses = $db->fetchAll($sql, $params);

echo json_encode(['success' => true, 'courses' => $courses]);
