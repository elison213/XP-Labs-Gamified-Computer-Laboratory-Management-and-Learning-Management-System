<?php
/**
 * XPLabs - Courses Hub
 * Role-aware course listing, lesson posting, and admin course management.
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::require();

$db = Database::getInstance();
$role = Auth::role();
$userId = Auth::id();
$message = null;

// Ensure lessons table exists so page works after deploy.
if (!$db->tableExists('course_lessons')) {
    $db->query(
        "CREATE TABLE IF NOT EXISTS course_lessons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            content TEXT NOT NULL,
            posted_by INT NOT NULL,
            published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
// Ensure attachment column exists for lesson file uploads.
$attachmentColumnExists = (int) $db->fetchOne(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'course_lessons' AND column_name = 'attachment_url'"
);
if ($attachmentColumnExists === 0) {
    $db->query("ALTER TABLE course_lessons ADD COLUMN attachment_url VARCHAR(500) DEFAULT NULL AFTER content");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create_course' && $role === 'admin') {
            $code = trim($_POST['code'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $subject = trim($_POST['subject'] ?? 'other');
            $teacherId = (int) ($_POST['teacher_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $targetGrade = trim($_POST['target_grade'] ?? '');
            $targetSection = trim($_POST['target_section'] ?? '');
            $academicYear = trim($_POST['academic_year'] ?? '');
            $semester = trim($_POST['semester'] ?? '');

            if ($code === '' || $name === '' || $teacherId <= 0) {
                throw new \RuntimeException('Code, name, and teacher are required.');
            }

            $teacher = $db->fetch("SELECT id FROM users WHERE id = ? AND role = 'teacher' AND is_active = 1", [$teacherId]);
            if (!$teacher) {
                throw new \RuntimeException('Selected teacher is invalid.');
            }

            $db->insert('courses', [
                'code' => $code,
                'name' => $name,
                'subject' => $subject,
                'description' => $description !== '' ? $description : null,
                'teacher_id' => $teacherId,
                'target_grade' => $targetGrade !== '' ? $targetGrade : null,
                'target_section' => $targetSection !== '' ? $targetSection : null,
                'academic_year' => $academicYear !== '' ? $academicYear : null,
                'semester' => $semester !== '' ? $semester : null,
                'status' => 'active',
            ]);
            $message = ['type' => 'success', 'text' => 'Course created successfully.'];
        } elseif ($action === 'delete_course' && $role === 'admin') {
            $courseId = (int) ($_POST['course_id'] ?? 0);
            if ($courseId <= 0) {
                throw new \RuntimeException('Invalid course.');
            }
            $db->delete('courses', 'id = ?', [$courseId]);
            $message = ['type' => 'success', 'text' => 'Course deleted successfully.'];
        } elseif ($action === 'add_lesson' && in_array($role, ['teacher', 'admin'], true)) {
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $attachmentUrl = null;

            if ($courseId <= 0 || $title === '' || $content === '') {
                throw new \RuntimeException('Course, title, and content are required.');
            }

            $course = $db->fetch(
                $role === 'teacher'
                    ? "SELECT id FROM courses WHERE id = ? AND teacher_id = ?"
                    : "SELECT id FROM courses WHERE id = ?",
                $role === 'teacher' ? [$courseId, $userId] : [$courseId]
            );
            if (!$course) {
                throw new \RuntimeException('You can only add lessons to your assigned courses.');
            }

            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                    throw new \RuntimeException('Attachment upload failed.');
                }
                $maxSize = 10 * 1024 * 1024; // 10 MB
                if ((int) $_FILES['attachment']['size'] > $maxSize) {
                    throw new \RuntimeException('Attachment exceeds 10 MB limit.');
                }

                $allowedMimes = [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'text/plain',
                    'application/zip',
                    'image/jpeg',
                    'image/png',
                ];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['attachment']['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowedMimes, true)) {
                    throw new \RuntimeException('Unsupported attachment file type.');
                }

                $uploadDir = __DIR__ . '/uploads/lessons/';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    throw new \RuntimeException('Failed to create lesson upload directory.');
                }

                $mimeToExt = [
                    'application/pdf' => 'pdf',
                    'application/msword' => 'doc',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                    'application/vnd.ms-excel' => 'xls',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                    'application/vnd.ms-powerpoint' => 'ppt',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                    'text/plain' => 'txt',
                    'application/zip' => 'zip',
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                ];
                $safeExt = $mimeToExt[$mime] ?? null;
                if (!$safeExt) {
                    throw new \RuntimeException('Unsupported attachment file type.');
                }
                $newName = uniqid('lesson_', true) . '.' . $safeExt;
                $destination = $uploadDir . $newName;
                if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $destination)) {
                    throw new \RuntimeException('Failed to save attachment.');
                }
                $attachmentUrl = 'uploads/lessons/' . $newName;
            }

            $db->insert('course_lessons', [
                'course_id' => $courseId,
                'title' => $title,
                'content' => $content,
                'attachment_url' => $attachmentUrl,
                'posted_by' => $userId,
            ]);
            $message = ['type' => 'success', 'text' => 'Lesson posted successfully.'];
        }
    } catch (\Throwable $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}

if ($role === 'student') {
    $courses = $db->fetchAll(
        "SELECT c.*, u.first_name AS teacher_first, u.last_name AS teacher_last
         FROM course_enrollments ce
         JOIN courses c ON c.id = ce.course_id
         LEFT JOIN users u ON c.teacher_id = u.id
         WHERE ce.user_id = ? AND ce.status = 'enrolled'
         ORDER BY c.name",
        [$userId]
    );
} elseif ($role === 'teacher') {
    $courses = $db->fetchAll(
        "SELECT c.*, COUNT(ce.id) AS student_count
         FROM courses c
         LEFT JOIN course_enrollments ce ON ce.course_id = c.id AND ce.status = 'enrolled'
         WHERE c.teacher_id = ?
         GROUP BY c.id
         ORDER BY c.name",
        [$userId]
    );
} else {
    $courses = $db->fetchAll(
        "SELECT c.*, u.first_name AS teacher_first, u.last_name AS teacher_last, COUNT(ce.id) AS student_count
         FROM courses c
         LEFT JOIN users u ON u.id = c.teacher_id
         LEFT JOIN course_enrollments ce ON ce.course_id = c.id AND ce.status = 'enrolled'
         GROUP BY c.id
         ORDER BY c.name"
    );
}

$courseIds = array_map(fn($c) => (int) $c['id'], $courses);
$lessonsByCourse = [];
$quizzesByCourse = [];
if (!empty($courseIds)) {
    $in = implode(',', $courseIds);
    $lessons = $db->fetchAll(
        "SELECT cl.*, u.first_name, u.last_name
         FROM course_lessons cl
         JOIN users u ON u.id = cl.posted_by
         WHERE cl.course_id IN ($in)
         ORDER BY cl.published_at DESC
         LIMIT 200"
    );
    foreach ($lessons as $lesson) {
        $lessonsByCourse[$lesson['course_id']][] = $lesson;
    }

    if ($role === 'student') {
        $quizzes = $db->fetchAll(
            "SELECT q.id, q.course_id, q.title, q.status, q.scheduled_at, q.closes_at,
                    latest.latest_completed_attempt_id
             FROM quizzes q
             LEFT JOIN (
                SELECT quiz_id, MAX(id) AS latest_completed_attempt_id
                FROM quiz_attempts
                WHERE user_id = ? AND status = 'completed'
                GROUP BY quiz_id
             ) latest ON latest.quiz_id = q.id
             WHERE q.course_id IN ($in) AND q.status IN ('active','scheduled','completed','archived')
             ORDER BY q.created_at DESC",
            [$userId]
        );
    } else {
        $quizzes = $db->fetchAll(
            "SELECT q.id, q.course_id, q.title, q.status, q.scheduled_at, q.closes_at
             FROM quizzes q
             WHERE q.course_id IN ($in)
             ORDER BY q.created_at DESC"
        );
    }
    foreach ($quizzes as $quiz) {
        $quizzesByCourse[$quiz['course_id']][] = $quiz;
    }
}

$teacherOptions = $role === 'admin'
    ? $db->fetchAll("SELECT id, first_name, last_name FROM users WHERE role = 'teacher' AND is_active = 1 ORDER BY last_name, first_name")
    : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courses - XPLabs</title>
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
    </style>
</head>
<body style="background:#f1f5f9;">
<?php if ($role === 'student') { include __DIR__ . '/components/student_sidebar.php'; } else { include __DIR__ . '/components/admin_sidebar.php'; } ?>
<div style="margin-left:260px; padding:2rem;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0"><i class="bi bi-journal-bookmark me-2"></i>Courses</h3>
            <small class="text-muted">
                <?php if ($role === 'student'): ?>Your enrolled courses and lessons
                <?php elseif ($role === 'teacher'): ?>Your assigned courses and lessons
                <?php else: ?>Manage all courses and lessons
                <?php endif; ?>
            </small>
        </div>
        <?php if ($role === 'admin'): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCourseModal">
            <i class="bi bi-plus-lg me-1"></i> Add Course
        </button>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['text']) ?></div>
    <?php endif; ?>

    <?php if (empty($courses)): ?>
        <div class="alert alert-info">No courses available.</div>
    <?php endif; ?>

    <?php foreach ($courses as $course): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <strong><?= e($course['code']) ?> - <?= e($course['name']) ?></strong>
                <div class="small text-muted">
                    <?php if ($role === 'student'): ?>
                        Teacher: <?= e(($course['teacher_first'] ?? '') . ' ' . ($course['teacher_last'] ?? '')) ?>
                    <?php else: ?>
                        Students: <?= (int) ($course['student_count'] ?? 0) ?>
                        <?php if ($role === 'admin'): ?>
                            | Teacher: <?= e(($course['teacher_first'] ?? '') . ' ' . ($course['teacher_last'] ?? '')) ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($role === 'admin'): ?>
            <form method="POST" onsubmit="return confirm('Delete this course permanently? This also removes enrollments and lessons.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_course">
                <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Delete Course</button>
            </form>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (in_array($role, ['teacher', 'admin'], true) && ($role === 'admin' || (int) $course['teacher_id'] === (int) $userId)): ?>
            <form method="POST" enctype="multipart/form-data" class="border rounded p-3 mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_lesson">
                <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
                <div class="mb-2">
                    <label class="form-label mb-1">Lesson Title</label>
                    <input name="title" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label mb-1">Lesson Content</label>
                    <textarea name="content" class="form-control" rows="3" required></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label mb-1">Attachment (optional)</label>
                    <input type="file" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.jpg,.jpeg,.png">
                    <small class="text-muted">Max 10 MB. Allowed: PDF, Office docs, text, zip, images.</small>
                </div>
                <button class="btn btn-sm btn-success">Post Lesson</button>
            </form>
            <?php endif; ?>

            <h6 class="mb-2">Lessons</h6>
            <?php $courseLessons = $lessonsByCourse[$course['id']] ?? []; ?>
            <?php if (empty($courseLessons)): ?>
                <div class="text-muted small">No lessons yet.</div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($courseLessons as $lesson): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong><?= e($lesson['title']) ?></strong>
                            <small class="text-muted"><?= date('M j, Y g:i A', strtotime($lesson['published_at'])) ?></small>
                        </div>
                        <div class="small text-muted mb-1">By <?= e($lesson['first_name'] . ' ' . $lesson['last_name']) ?></div>
                        <div><?= nl2br(e($lesson['content'])) ?></div>
                        <?php if (!empty($lesson['attachment_url'])): ?>
                        <div class="mt-2">
                            <a href="<?= e($lesson['attachment_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-paperclip me-1"></i> Open Attachment
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h6 class="mt-4 mb-2">Quizzes</h6>
            <?php $courseQuizzes = $quizzesByCourse[$course['id']] ?? []; ?>
            <?php if (empty($courseQuizzes)): ?>
                <div class="text-muted small">No quizzes available for this course.</div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($courseQuizzes as $quiz): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= e($quiz['title']) ?></strong>
                            <div class="small text-muted">Status: <?= e(ucfirst($quiz['status'])) ?></div>
                        </div>
                        <?php if ($role === 'student' && $quiz['status'] === 'active'): ?>
                        <div class="d-flex gap-2">
                            <a href="quiz_attempt.php?quiz_id=<?= (int) $quiz['id'] ?>" class="btn btn-sm btn-primary">Take Quiz</a>
                            <?php if (!empty($quiz['latest_completed_attempt_id'])): ?>
                            <a href="quiz_review.php?attempt_id=<?= (int) $quiz['latest_completed_attempt_id'] ?>" class="btn btn-sm btn-outline-success">Review Right/Wrong</a>
                            <?php endif; ?>
                        </div>
                        <?php elseif ($role === 'student' && !empty($quiz['latest_completed_attempt_id'])): ?>
                        <a href="quiz_review.php?attempt_id=<?= (int) $quiz['latest_completed_attempt_id'] ?>" class="btn btn-sm btn-outline-success">Review Right/Wrong</a>
                        <?php else: ?>
                        <a href="my_quizzes.php" class="btn btn-sm btn-outline-secondary">View</a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($role === 'admin'): ?>
<div class="modal fade" id="createCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_course">
            <div class="modal-header">
                <h5 class="modal-title">Add Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Code</label>
                    <input name="code" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Name</label>
                    <input name="name" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Subject</label>
                    <select name="subject" class="form-select">
                        <option value="computer_programming">Computer Programming</option>
                        <option value="web_development">Web Development</option>
                        <option value="visual_graphics">Visual Graphics</option>
                        <option value="it_fundamentals">IT Fundamentals</option>
                        <option value="cs_concepts">CS Concepts</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Teacher</label>
                    <select name="teacher_id" class="form-select" required>
                        <option value="">Select teacher...</option>
                        <?php foreach ($teacherOptions as $teacher): ?>
                        <option value="<?= (int) $teacher['id'] ?>"><?= e($teacher['last_name'] . ', ' . $teacher['first_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <div class="row g-2">
                    <div class="col">
                        <label class="form-label">Target Grade</label>
                        <input name="target_grade" class="form-control">
                    </div>
                    <div class="col">
                        <label class="form-label">Target Section</label>
                        <input name="target_section" class="form-control">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col">
                        <label class="form-label">Academic Year</label>
                        <input name="academic_year" class="form-control" placeholder="2026-2027">
                    </div>
                    <div class="col">
                        <label class="form-label">Semester</label>
                        <input name="semester" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Course</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

