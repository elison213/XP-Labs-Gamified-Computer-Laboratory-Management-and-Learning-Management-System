<?php
/**
 * XPLabs - Course Access Middleware
 * Ensures users can only access courses they're enrolled in or teach.
 */

namespace XPLabs\Middleware;

use XPLabs\Lib\Database;
use XPLabs\Lib\Auth;

class CourseAccessMiddleware
{
    /**
     * Check if the current user can access a course.
     */
    public static function canAccess(int $courseId): bool
    {
        $userId = Auth::id();
        if (!$userId) {
            return false;
        }

        $db = Database::getInstance();

        // Get the course
        $course = $db->fetch("SELECT * FROM courses WHERE id = ?", [$courseId]);
        if (!$course) {
            return false;
        }

        // Teachers who own this course → always access
        if ($course['teacher_id'] === $userId) {
            return true;
        }

        // Admins → always access
        $user = $db->fetch("SELECT role FROM users WHERE id = ?", [$userId]);
        if ($user && $user['role'] === 'admin') {
            return true;
        }

        // Students → check enrollment
        $enrollment = $db->fetch(
            "SELECT status FROM course_enrollments WHERE course_id = ? AND user_id = ? AND status = 'enrolled'",
            [$courseId, $userId]
        );
        return (bool) $enrollment;
    }

    /**
     * Require access to a course or return 403.
     */
    public static function requireAccess(int $courseId): void
    {
        if (!self::canAccess($courseId)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Access denied. You are not enrolled in or assigned to this course.']);
            exit;
        }
    }

    /**
     * Get the course ID from request (POST body or query string).
     */
    public static function getCourseIdFromRequest(): ?int
    {
        // Try POST body first
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['course_id'])) {
            return (int) $input['course_id'];
        }

        // Try query string
        if (isset($_GET['course_id'])) {
            return (int) $_GET['course_id'];
        }

        // Try URL path parameter
        if (isset($_GET['id'])) {
            return (int) $_GET['id'];
        }

        return null;
    }
}