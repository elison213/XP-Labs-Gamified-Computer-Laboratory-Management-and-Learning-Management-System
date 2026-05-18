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
$hasAttemptIsPreview = (int) $db->fetchOne(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'quiz_attempts'
       AND column_name = 'is_preview'"
) > 0;

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
            $targetSection = trim($_POST['target_section'] ?? '');
            $academicYear = trim($_POST['academic_year'] ?? '');
            $gradingTrack = trim($_POST['grading_track'] ?? 'semester');
            $gradingPeriodIndex = isset($_POST['grading_period_index']) ? (int) $_POST['grading_period_index'] : 0;

            if ($code === '' || $name === '' || $teacherId <= 0) {
                throw new \RuntimeException('Code, name, and teacher are required.');
            }

            if (!in_array($gradingTrack, ['quarter', 'semester'], true)) {
                $gradingTrack = 'semester';
            }
            if ($gradingTrack === 'quarter') {
                if ($gradingPeriodIndex < 1 || $gradingPeriodIndex > 4) {
                    throw new \RuntimeException('Select a valid quarter (1st–4th).');
                }
            } elseif ($gradingPeriodIndex < 1 || $gradingPeriodIndex > 2) {
                throw new \RuntimeException('Select a valid semester (1st or 2nd).');
            }

            $semesterLabel = '';
            if ($gradingTrack === 'quarter') {
                $ords = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'];
                $semesterLabel = $ords[$gradingPeriodIndex] . ' Quarter';
            } else {
                $semesterLabel = $gradingPeriodIndex === 1 ? '1st Semester' : '2nd Semester';
            }

            $teacher = $db->fetch("SELECT id FROM users WHERE id = ? AND role = 'teacher' AND is_active = 1", [$teacherId]);
            if (!$teacher) {
                throw new \RuntimeException('Selected teacher is invalid.');
            }

            $insert = [
                'code' => $code,
                'name' => $name,
                'subject' => $subject,
                'description' => $description !== '' ? $description : null,
                'teacher_id' => $teacherId,
                'target_grade' => null,
                'target_section' => $targetSection !== '' ? $targetSection : null,
                'academic_year' => $academicYear !== '' ? $academicYear : null,
                'semester' => $semesterLabel !== '' ? $semesterLabel : null,
                'status' => 'active',
            ];

            $hasGradingCols = (int) $db->fetchOne(
                "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'courses' AND column_name = 'grading_track'"
            ) > 0;
            if ($hasGradingCols) {
                $insert['grading_track'] = $gradingTrack;
                $insert['grading_period_index'] = $gradingPeriodIndex;
            }

            $db->insert('courses', $insert);
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
$assignmentsByCourse = [];
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
        $attemptFilter = $hasAttemptIsPreview ? " AND COALESCE(is_preview, 0) = 0" : "";
        $quizzes = $db->fetchAll(
            "SELECT q.id, q.course_id, q.title, q.status, q.scheduled_at, q.closes_at,
                    latest.latest_completed_attempt_id
             FROM quizzes q
             LEFT JOIN (
                SELECT quiz_id, MAX(id) AS latest_completed_attempt_id
                FROM quiz_attempts
                WHERE user_id = ? AND status = 'completed'" . $attemptFilter . "
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

    if ($db->tableExists('assignments')) {
        if ($role === 'student') {
            $assignments = $db->fetchAll(
                "SELECT a.id, a.course_id, a.title, a.description, a.due_date, a.created_at, a.max_points, a.status,
                        s.id AS submission_id, s.status AS submission_status, s.submitted_at
                 FROM assignments a
                 LEFT JOIN submissions s ON s.assignment_id = a.id AND s.user_id = ?
                 WHERE a.course_id IN ($in) AND a.status IN ('published', 'draft')
                 ORDER BY a.created_at DESC",
                [$userId]
            );
        } else {
            $assignments = $db->fetchAll(
                "SELECT a.id, a.course_id, a.title, a.description, a.due_date, a.created_at, a.max_points, a.status,
                        COUNT(s.id) AS submission_count
                 FROM assignments a
                 LEFT JOIN submissions s ON s.assignment_id = a.id
                 WHERE a.course_id IN ($in) AND a.status != 'archived'
                 GROUP BY a.id
                 ORDER BY a.created_at DESC"
            );
        }
        foreach ($assignments as $assignment) {
            $assignmentsByCourse[(int) $assignment['course_id']][] = $assignment;
        }
    }
}

$lessonTotalsByCourse = [];
$lessonDoneByCourse = [];
if ($role === 'student' && $db->tableExists('course_lesson_progress') && !empty($courseIds)) {
    $in = implode(',', $courseIds);
    foreach ($db->fetchAll("SELECT course_id, COUNT(*) AS n FROM course_lessons WHERE course_id IN ($in) GROUP BY course_id") as $row) {
        $lessonTotalsByCourse[(int) $row['course_id']] = (int) $row['n'];
    }
    foreach ($db->fetchAll(
        "SELECT course_id, COUNT(*) AS n FROM course_lesson_progress WHERE user_id = ? AND course_id IN ($in) GROUP BY course_id",
        [$userId]
    ) as $row) {
        $lessonDoneByCourse[(int) $row['course_id']] = (int) $row['n'];
    }
}

$streamByCourse = [];
foreach ($courseIds as $courseId) {
    $stream = [];
    foreach (($lessonsByCourse[$courseId] ?? []) as $lesson) {
        $stream[] = [
            'kind' => 'lesson',
            'posted_at' => (string) ($lesson['published_at'] ?? $lesson['created_at'] ?? ''),
            'row' => $lesson,
        ];
    }
    foreach (($quizzesByCourse[$courseId] ?? []) as $quiz) {
        $stream[] = [
            'kind' => 'quiz',
            'posted_at' => (string) ($quiz['created_at'] ?? $quiz['scheduled_at'] ?? ''),
            'row' => $quiz,
        ];
    }
    foreach (($assignmentsByCourse[$courseId] ?? []) as $assignment) {
        $stream[] = [
            'kind' => 'assignment',
            'posted_at' => (string) ($assignment['created_at'] ?? ''),
            'row' => $assignment,
        ];
    }
    usort($stream, static function (array $a, array $b): int {
        return strtotime($b['posted_at'] ?: '1970-01-01') <=> strtotime($a['posted_at'] ?: '1970-01-01');
    });
    $streamByCourse[$courseId] = $stream;
}

$teacherOptions = $role === 'admin'
    ? $db->fetchAll("SELECT id, first_name, last_name FROM users WHERE role = 'teacher' AND is_active = 1 ORDER BY last_name, first_name")
    : [];

function course_lesson_excerpt(string $text, int $max = 200): string
{
    $t = trim(preg_replace('/\s+/', ' ', $text));
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($t) > $max ? mb_substr($t, 0, $max) . '…' : $t;
    }

    return strlen($t) > $max ? substr($t, 0, $max) . '…' : $t;
}

/** @param array<string,mixed> $course */
function course_grading_badge(array $course): string
{
    $track = $course['grading_track'] ?? 'semester';
    $idx = isset($course['grading_period_index']) ? (int) $course['grading_period_index'] : null;
    if ($track === 'quarter' && $idx !== null && $idx >= 1 && $idx <= 4) {
        $ords = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'];

        return 'Quarterly (JHS) · ' . $ords[$idx] . ' Quarter';
    }
    if ($track === 'semester' && $idx !== null && $idx >= 1 && $idx <= 2) {
        return 'Semester (SHS) · ' . ($idx === 1 ? '1st Semester' : '2nd Semester');
    }
    $sem = trim((string) ($course['semester'] ?? ''));

    return $sem !== '' ? $sem : '—';
}

function course_item_when(?string $date): string
{
    if (!$date) {
        return 'Date not set';
    }
    $ts = strtotime($date);
    if (!$ts) {
        return $date;
    }

    return date('M j, Y g:i A', $ts);
}
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
        .course-tile .accordion-button {
            box-shadow: none; background: #fff; padding: 1rem 1.25rem;
        }
        .course-tile .accordion-button:not(.collapsed) {
            background: #f8fafc; border-bottom: 1px solid #e2e8f0;
        }
        .course-tile .accordion-body { background: #fff; }
        .course-tile-meta { font-size: 0.8rem; color: #64748b; }
        .course-tile-progress { height: 6px; border-radius: 4px; }
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

    <div class="accordion" id="coursesAccordion">
    <?php foreach ($courses as $course): ?>
        <?php
        $cid = (int) $course['id'];
        $collapseId = 'courseCollapse' . $cid;
        $tabsId = 'courseTabs' . $cid;
        $courseLessons = $lessonsByCourse[$cid] ?? [];
        $courseQuizzes = $quizzesByCourse[$cid] ?? [];
        $courseAssignments = $assignmentsByCourse[$cid] ?? [];
        $courseStream = $streamByCourse[$cid] ?? [];
        $totalLessons = $lessonTotalsByCourse[$cid] ?? 0;
        $doneLessons = $lessonDoneByCourse[$cid] ?? 0;
        $pct = $totalLessons > 0 ? (int) round(100 * $doneLessons / $totalLessons) : 0;
        ?>
    <div class="accordion-item course-tile border-0 shadow-sm mb-3 rounded overflow-hidden">
        <div class="accordion-header d-flex align-items-stretch border-bottom" id="heading<?= $cid ?>">
            <button class="accordion-button collapsed rounded-0 flex-grow-1" type="button" data-bs-toggle="collapse" data-bs-target="#<?= e($collapseId) ?>" aria-expanded="false" aria-controls="<?= e($collapseId) ?>">
                <div class="w-100 text-start">
                    <div class="fw-semibold text-dark"><?= e($course['code']) ?> — <?= e($course['name']) ?></div>
                    <div class="course-tile-meta mt-1">
                        <?php if ($role === 'student'): ?>
                            Teacher: <?= e(trim(($course['teacher_first'] ?? '') . ' ' . ($course['teacher_last'] ?? ''))) ?>
                            <?php $sec = trim((string) ($course['target_section'] ?? '')); ?>
                            <?= $sec !== '' ? ' · Section ' . e($sec) : '' ?>
                            · <?= e(course_grading_badge($course)) ?>
                        <?php else: ?>
                            <?= (int) ($course['student_count'] ?? 0) ?> students
                            <?php if ($role === 'admin'): ?>
                                · <?= e(trim(($course['teacher_first'] ?? '') . ' ' . ($course['teacher_last'] ?? ''))) ?>
                            <?php endif; ?>
                            <?php $sec = trim((string) ($course['target_section'] ?? '')); ?>
                            <?= $sec !== '' ? ' · Section ' . e($sec) : '' ?>
                            · <?= e(course_grading_badge($course)) ?>
                        <?php endif; ?>
                        · <?= count($courseLessons) ?> lessons · <?= count($courseAssignments) ?> assignments · <?= count($courseQuizzes) ?> quizzes
                    </div>
                    <?php if ($role === 'student' && $totalLessons > 0): ?>
                    <div class="mt-2 pe-2">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Lesson progress</span>
                            <span><?= (int) $doneLessons ?> / <?= (int) $totalLessons ?> (<?= $pct ?>%)</span>
                        </div>
                        <div class="progress course-tile-progress">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <?php elseif ($role === 'student'): ?>
                    <div class="small text-muted mt-2">No lessons posted yet.</div>
                    <?php endif; ?>
                </div>
            </button>
            <?php if ($role === 'admin'): ?>
            <div class="d-flex align-items-center px-2 bg-white border-start">
                <form method="POST" class="mb-0" onsubmit="return confirm('Delete this course permanently? This also removes enrollments and lessons.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_course">
                    <input type="hidden" name="course_id" value="<?= $cid ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <div id="<?= e($collapseId) ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $cid ?>">
            <div class="accordion-body pt-3">
                <ul class="nav nav-tabs mb-3" id="<?= e($tabsId) ?>" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="stream-tab-<?= $cid ?>" data-bs-toggle="tab" data-bs-target="#stream-pane-<?= $cid ?>" type="button" role="tab">Stream</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="lessons-tab-<?= $cid ?>" data-bs-toggle="tab" data-bs-target="#lessons-pane-<?= $cid ?>" type="button" role="tab">Lessons</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="quizzes-tab-<?= $cid ?>" data-bs-toggle="tab" data-bs-target="#quizzes-pane-<?= $cid ?>" type="button" role="tab">Quizzes</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="assignments-tab-<?= $cid ?>" data-bs-toggle="tab" data-bs-target="#assignments-pane-<?= $cid ?>" type="button" role="tab">Assignments</button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="stream-pane-<?= $cid ?>" role="tabpanel">
                        <?php if (empty($courseStream)): ?>
                            <div class="text-muted small">No posts yet for this course.</div>
                        <?php else: ?>
                            <div class="list-group border rounded">
                                <?php foreach ($courseStream as $item): ?>
                                    <?php if ($item['kind'] === 'lesson'): $lesson = $item['row']; ?>
                                        <a class="list-group-item list-group-item-action py-3 text-decoration-none" href="course_lesson.php?course_id=<?= $cid ?>&lesson_id=<?= (int) $lesson['id'] ?>">
                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                <div>
                                                    <div class="fw-semibold text-dark"><span class="badge text-bg-primary me-2">Lesson</span><?= e($lesson['title']) ?></div>
                                                    <div class="small text-muted">Posted <?= e(course_item_when((string) ($lesson['published_at'] ?? null))) ?></div>
                                                    <div class="small text-secondary"><?= e(course_lesson_excerpt((string) $lesson['content'], 160)) ?></div>
                                                </div>
                                                <span class="text-primary small text-nowrap"><i class="bi bi-arrow-right-circle"></i> Open</span>
                                            </div>
                                        </a>
                                    <?php elseif ($item['kind'] === 'assignment'): $a = $item['row']; ?>
                                        <div class="list-group-item py-3">
                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                <div>
                                                    <div class="fw-semibold text-dark"><span class="badge text-bg-warning me-2">Assignment</span><?= e($a['title']) ?></div>
                                                    <div class="small text-muted">Posted <?= e(course_item_when((string) ($a['created_at'] ?? null))) ?> · Due <?= e(course_item_when((string) ($a['due_date'] ?? null))) ?></div>
                                                </div>
                                                <?php if ($role === 'student'): ?>
                                                    <a href="submission.php?assignment=<?= (int) $a['id'] ?>" class="btn btn-sm btn-outline-primary">Open</a>
                                                <?php else: ?>
                                                    <a href="submissions.php?course_id=<?= $cid ?>" class="btn btn-sm btn-outline-primary">View Submissions</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: $quiz = $item['row']; ?>
                                        <div class="list-group-item py-3">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                <div>
                                                    <div class="fw-semibold"><span class="badge text-bg-info me-2">Quiz</span><?= e($quiz['title']) ?></div>
                                                    <div class="small text-muted">Posted <?= e(course_item_when((string) ($quiz['created_at'] ?? $quiz['scheduled_at'] ?? null))) ?> · Status <?= e(ucfirst((string) $quiz['status'])) ?></div>
                                                </div>
                                                <?php if ($role === 'student' && $quiz['status'] === 'active'): ?>
                                                    <a href="quiz_attempt.php?quiz_id=<?= (int) $quiz['id'] ?>" class="btn btn-sm btn-primary">Take Quiz</a>
                                                <?php elseif ($role === 'student' && !empty($quiz['latest_completed_attempt_id'])): ?>
                                                    <a href="quiz_review.php?attempt_id=<?= (int) $quiz['latest_completed_attempt_id'] ?>" class="btn btn-sm btn-outline-success">Review</a>
                                                <?php else: ?>
                                                    <a href="quiz_attempt.php?quiz_id=<?= (int) $quiz['id'] ?>&preview=1" class="btn btn-sm btn-outline-info">Preview</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="tab-pane fade" id="lessons-pane-<?= $cid ?>" role="tabpanel">
                        <?php if (in_array($role, ['teacher', 'admin'], true) && ($role === 'admin' || (int) $course['teacher_id'] === (int) $userId)): ?>
                        <form method="POST" enctype="multipart/form-data" class="border rounded p-3 mb-3 bg-light">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_lesson">
                            <input type="hidden" name="course_id" value="<?= $cid ?>">
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

                        <?php if (empty($courseLessons)): ?>
                            <div class="text-muted small">No lessons yet.</div>
                        <?php else: ?>
                            <div class="list-group list-group-flush border rounded">
                                <?php foreach ($courseLessons as $lesson): ?>
                                <a class="list-group-item list-group-item-action py-3 text-decoration-none"
                                   href="course_lesson.php?course_id=<?= $cid ?>&lesson_id=<?= (int) $lesson['id'] ?>">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-semibold text-dark"><?= e($lesson['title']) ?></div>
                                            <div class="small text-muted mb-1">By <?= e($lesson['first_name'] . ' ' . $lesson['last_name']) ?> · <?= date('M j, Y g:i A', strtotime($lesson['published_at'])) ?></div>
                                            <div class="small text-secondary"><?= e(course_lesson_excerpt((string) $lesson['content'])) ?></div>
                                        </div>
                                        <span class="text-primary small text-nowrap"><i class="bi bi-arrow-right-circle"></i> Open</span>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="tab-pane fade" id="quizzes-pane-<?= $cid ?>" role="tabpanel">
                        <?php if (empty($courseQuizzes)): ?>
                            <div class="text-muted small">No quizzes available for this course.</div>
                        <?php else: ?>
                            <div class="list-group border rounded">
                                <?php foreach ($courseQuizzes as $quiz): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <strong><?= e($quiz['title']) ?></strong>
                                        <div class="small text-muted">Status: <?= e(ucfirst($quiz['status'])) ?></div>
                                    </div>
                                    <?php if ($role === 'student' && $quiz['status'] === 'active'): ?>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="quiz_attempt.php?quiz_id=<?= (int) $quiz['id'] ?>" class="btn btn-sm btn-primary">Take Quiz</a>
                                        <?php if (!empty($quiz['latest_completed_attempt_id'])): ?>
                                        <a href="quiz_review.php?attempt_id=<?= (int) $quiz['latest_completed_attempt_id'] ?>" class="btn btn-sm btn-outline-success">Review Right/Wrong</a>
                                        <?php endif; ?>
                                    </div>
                                    <?php elseif ($role === 'student' && !empty($quiz['latest_completed_attempt_id'])): ?>
                                    <a href="quiz_review.php?attempt_id=<?= (int) $quiz['latest_completed_attempt_id'] ?>" class="btn btn-sm btn-outline-success">Review Right/Wrong</a>
                                    <?php else: ?>
                                    <a href="quiz_attempt.php?quiz_id=<?= (int) $quiz['id'] ?>&preview=1" class="btn btn-sm btn-outline-info"><i class="bi bi-play-circle me-1"></i>Preview</a>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="tab-pane fade" id="assignments-pane-<?= $cid ?>" role="tabpanel">
                        <?php if (empty($courseAssignments)): ?>
                            <div class="text-muted small">No assignments available for this course.</div>
                        <?php else: ?>
                            <div class="list-group border rounded">
                                <?php foreach ($courseAssignments as $a): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <strong><?= e($a['title']) ?></strong>
                                        <div class="small text-muted">
                                            Posted <?= e(course_item_when((string) ($a['created_at'] ?? null))) ?>
                                            · Due <?= e(course_item_when((string) ($a['due_date'] ?? null))) ?>
                                            <?php if ($role === 'student' && !empty($a['submission_status'])): ?>
                                                · Your status: <?= e(ucfirst((string) $a['submission_status'])) ?>
                                            <?php elseif ($role !== 'student'): ?>
                                                · Submissions: <?= (int) ($a['submission_count'] ?? 0) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($role === 'student'): ?>
                                        <a href="submission.php?assignment=<?= (int) $a['id'] ?>" class="btn btn-sm btn-outline-primary">Open</a>
                                    <?php else: ?>
                                        <a href="submissions.php?course_id=<?= $cid ?>" class="btn btn-sm btn-outline-primary">View Submissions</a>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
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
                <div class="mb-2">
                    <label class="form-label">Target Section</label>
                    <input name="target_section" class="form-control" placeholder="e.g., Newton">
                </div>
                <div class="mb-2">
                    <label class="form-label">Academic Year</label>
                    <input name="academic_year" class="form-control" placeholder="2026-2027">
                </div>
                <div class="mb-2">
                    <label class="form-label">Grading</label>
                    <select name="grading_track" id="courseGradingTrack" class="form-select">
                        <option value="quarter">Quarterly (JHS)</option>
                        <option value="semester" selected>Semester (SHS)</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Period</label>
                    <select name="grading_period_index" id="courseGradingPeriod" class="form-select"></select>
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
<script>
(function () {
    const track = document.getElementById('courseGradingTrack');
    const period = document.getElementById('courseGradingPeriod');
    if (!track || !period) return;
    function syncPeriodOptions() {
        const v = track.value;
        period.innerHTML = '';
        if (v === 'quarter') {
            [['1','1st Quarter'],['2','2nd Quarter'],['3','3rd Quarter'],['4','4th Quarter']].forEach(function (o) {
                const opt = document.createElement('option');
                opt.value = o[0]; opt.textContent = o[1]; period.appendChild(opt);
            });
        } else {
            [['1','1st Semester'],['2','2nd Semester']].forEach(function (o) {
                const opt = document.createElement('option');
                opt.value = o[0]; opt.textContent = o[1]; period.appendChild(opt);
            });
        }
    }
    track.addEventListener('change', syncPeriodOptions);
    syncPeriodOptions();
})();
</script>
</body>
</html>

