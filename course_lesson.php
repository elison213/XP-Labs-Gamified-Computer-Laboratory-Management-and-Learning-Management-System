<?php
/**
 * XPLabs - Single lesson view (keeps same sidebar layout as Courses).
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::require();

$db = Database::getInstance();
$role = Auth::role();
$userId = Auth::id();
$courseId = (int) ($_GET['course_id'] ?? 0);
$lessonId = (int) ($_GET['lesson_id'] ?? 0);

if ($courseId <= 0 || $lessonId <= 0) {
    header('Location: courses.php');
    exit;
}

$lesson = $db->fetch(
    "SELECT cl.*, u.first_name, u.last_name, c.name AS course_name, c.code AS course_code
     FROM course_lessons cl
     JOIN users u ON u.id = cl.posted_by
     JOIN courses c ON c.id = cl.course_id
     WHERE cl.id = ? AND cl.course_id = ?",
    [$lessonId, $courseId]
);

if (!$lesson) {
    header('Location: courses.php');
    exit;
}

$course = $db->fetch('SELECT id, teacher_id, name, code FROM courses WHERE id = ?', [$courseId]);
if (!$course) {
    header('Location: courses.php');
    exit;
}

$allowed = false;
if ($role === 'admin') {
    $allowed = true;
} elseif ($role === 'teacher' && (int) $course['teacher_id'] === $userId) {
    $allowed = true;
} elseif ($role === 'student') {
    $en = $db->fetch(
        "SELECT id FROM course_enrollments WHERE course_id = ? AND user_id = ? AND status = 'enrolled'",
        [$courseId, $userId]
    );
    $allowed = $en !== null;
}

if (!$allowed) {
    http_response_code(403);
    $forbidden = true;
} else {
    $forbidden = false;
    if ($role === 'student' && $db->tableExists('course_lesson_progress')) {
        $db->query(
            'INSERT INTO course_lesson_progress (user_id, lesson_id, course_id, first_completed_at)
             VALUES (?, ?, ?, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE first_completed_at = first_completed_at',
            [$userId, $lessonId, $courseId]
        );
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($lesson['title']) ?> — <?= e($lesson['course_name']) ?> | XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            position: fixed; top: 0; left: 0; width: 260px; height: 100vh;
            background: #1e293b; border-right: 1px solid #334155; z-index: 1000; overflow-y: auto;
        }
        .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid #334155; }
        .sidebar-brand h4 { margin: 0; font-weight: 700; color: #fff; }
        .sidebar-brand small { color: #94a3b8; }
        .sidebar-nav { padding: 1rem 0; }
        .sidebar-nav a {
            display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem;
            color: #94a3b8; text-decoration: none; transition: all 0.2s;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: rgba(99, 102, 241, 0.1); color: #6366f1;
        }
        .sidebar-nav .nav-section {
            padding: 0.5rem 1.5rem; font-size: 0.7rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: #94a3b8; margin-top: 0.5rem;
        }
        .lesson-body { font-size: 1.05rem; line-height: 1.65; max-width: 72ch; }
    </style>
</head>
<body style="background:#f1f5f9;">
<?php if ($role === 'student') { include __DIR__ . '/components/student_sidebar.php'; } else { include __DIR__ . '/components/admin_sidebar.php'; } ?>
<div style="margin-left:260px; padding:2rem;">
    <?php if (!empty($forbidden)): ?>
        <div class="alert alert-danger">You do not have access to this lesson.</div>
        <a href="courses.php" class="btn btn-outline-secondary">Back to Courses</a>
    <?php else: ?>
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="courses.php">Courses</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($lesson['title']) ?></li>
            </ol>
        </nav>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h4 class="mb-1"><?= e($lesson['title']) ?></h4>
                <div class="text-muted small">
                    <?= e($lesson['course_code']) ?> — <?= e($lesson['course_name']) ?>
                    · <?= date('M j, Y g:i A', strtotime($lesson['published_at'])) ?>
                    · <?= e(trim($lesson['first_name'] . ' ' . $lesson['last_name'])) ?>
                </div>
            </div>
            <div class="card-body">
                <div class="lesson-body"><?= nl2br(e($lesson['content'])) ?></div>
                <?php if (!empty($lesson['attachment_url'])): ?>
                <div class="mt-4">
                    <a href="<?= e($lesson['attachment_url']) ?>" target="_blank" rel="noopener" class="btn btn-primary">
                        <i class="bi bi-paperclip me-1"></i> Open attachment
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
