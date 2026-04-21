<?php
/**
 * XPLabs - Course Enrollments Management
 * Allow admin/teacher to enroll students in courses.
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::requireRole(['admin', 'teacher']);

$db = Database::getInstance();
$role = Auth::role();
$actorId = Auth::id();
$message = null;

$courses = $db->fetchAll(
    $role === 'teacher'
        ? "SELECT id, code, name FROM courses WHERE teacher_id = ? AND status = 'active' ORDER BY name"
        : "SELECT id, code, name FROM courses WHERE status = 'active' ORDER BY name",
    $role === 'teacher' ? [$actorId] : []
);

$selectedCourseId = (int) ($_GET['course_id'] ?? ($_POST['course_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $studentId = (int) ($_POST['student_id'] ?? 0);

    $course = $db->fetch(
        $role === 'teacher'
            ? "SELECT id FROM courses WHERE id = ? AND teacher_id = ?"
            : "SELECT id FROM courses WHERE id = ?",
        $role === 'teacher' ? [$courseId, $actorId] : [$courseId]
    );

    if (!$course) {
        $message = ['type' => 'danger', 'text' => 'Invalid course selected.'];
    } else {
        try {
            if ($action === 'enroll') {
                $student = $db->fetch("SELECT id FROM users WHERE id = ? AND role = 'student' AND is_active = 1", [$studentId]);
                if (!$student) {
                    throw new \RuntimeException('Invalid student selected.');
                }

                $existing = $db->fetch(
                    "SELECT id FROM course_enrollments WHERE course_id = ? AND user_id = ?",
                    [$courseId, $studentId]
                );

                if ($existing) {
                    $db->update('course_enrollments', [
                        'status' => 'enrolled',
                        'enrolled_by' => $actorId,
                        'enrolled_at' => date('Y-m-d H:i:s'),
                        'completed_at' => null,
                    ], 'id = ?', [$existing['id']]);
                } else {
                    $db->insert('course_enrollments', [
                        'course_id' => $courseId,
                        'user_id' => $studentId,
                        'enrolled_by' => $actorId,
                        'status' => 'enrolled',
                    ]);
                }
                $message = ['type' => 'success', 'text' => 'Student enrolled successfully.'];
            } elseif ($action === 'drop') {
                $db->update('course_enrollments', [
                    'status' => 'dropped',
                    'completed_at' => date('Y-m-d H:i:s'),
                ], 'course_id = ? AND user_id = ?', [$courseId, $studentId]);
                $message = ['type' => 'success', 'text' => 'Student dropped from course.'];
            }
        } catch (\Throwable $e) {
            $message = ['type' => 'danger', 'text' => $e->getMessage()];
        }
    }

    $selectedCourseId = $courseId;
}

$students = $db->fetchAll(
    "SELECT id, lrn, first_name, last_name, grade_level, section
     FROM users
     WHERE role = 'student' AND is_active = 1
     ORDER BY last_name, first_name"
);

$enrolledStudents = [];
if ($selectedCourseId > 0) {
    $enrolledStudents = $db->fetchAll(
        "SELECT u.id, u.lrn, u.first_name, u.last_name, u.grade_level, u.section,
                ce.status, ce.enrolled_at
         FROM course_enrollments ce
         JOIN users u ON ce.user_id = u.id
         WHERE ce.course_id = ? AND ce.status = 'enrolled'
         ORDER BY u.last_name, u.first_name",
        [$selectedCourseId]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Enrollments - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body style="background:#f1f5f9;">
<?php include __DIR__ . '/components/admin_sidebar.php'; ?>
<div class="container-fluid" style="margin-left:260px; padding:2rem; max-width:calc(100% - 260px);">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0"><i class="bi bi-people me-2"></i>Course Enrollments</h3>
            <small class="text-muted">Assign students to courses</small>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['text']) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Select Course</label>
                    <select name="course_id" class="form-select" required>
                        <option value="">Choose a course...</option>
                        <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['id'] ?>" <?= $selectedCourseId === (int) $course['id'] ? 'selected' : '' ?>>
                            <?= e($course['code'] . ' - ' . $course['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Load</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedCourseId > 0): ?>
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><strong>Enroll Student</strong></div>
                <div class="card-body">
                    <form method="POST" class="row g-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="enroll">
                        <input type="hidden" name="course_id" value="<?= $selectedCourseId ?>">
                        <div class="col-12">
                            <label class="form-label">Student</label>
                            <select name="student_id" class="form-select" required>
                                <option value="">Select student...</option>
                                <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>">
                                    <?= e($student['last_name'] . ', ' . $student['first_name']) ?> (<?= e($student['lrn']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-person-plus me-1"></i> Enroll Student
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Enrolled Students</strong>
                    <span class="badge bg-primary"><?= count($enrolledStudents) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th class="px-3">Student</th>
                                    <th>LRN</th>
                                    <th>Grade/Section</th>
                                    <th>Enrolled</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrolledStudents as $student): ?>
                                <tr>
                                    <td class="px-3"><?= e($student['last_name'] . ', ' . $student['first_name']) ?></td>
                                    <td><?= e($student['lrn']) ?></td>
                                    <td><?= e(($student['grade_level'] ?? '-') . ' / ' . ($student['section'] ?? '-')) ?></td>
                                    <td><?= date('M j, Y', strtotime($student['enrolled_at'])) ?></td>
                                    <td class="text-end pe-3">
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Drop this student from course?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="drop">
                                            <input type="hidden" name="course_id" value="<?= $selectedCourseId ?>">
                                            <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Drop</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($enrolledStudents)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No enrolled students yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

