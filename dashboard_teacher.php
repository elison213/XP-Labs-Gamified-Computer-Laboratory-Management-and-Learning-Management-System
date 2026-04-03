<?php
/**
 * XPLabs - Teacher Dashboard
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Services\AttendanceService;
use XPLabs\Services\LabService;
use XPLabs\Services\QuizService;

Auth::requireRole('teacher');

$role = 'teacher';

$userId = Auth::id();
$db = Database::getInstance();
$labService = new LabService();
$attendanceService = new AttendanceService();
$quizService = new QuizService();

// Get teacher's courses
$teacherCourses = $db->fetchAll(
    "SELECT c.*, COUNT(ce.user_id) as student_count
     FROM courses c
     LEFT JOIN course_enrollments ce ON c.id = ce.course_id AND ce.status = 'enrolled'
     WHERE c.teacher_id = ? AND c.status = 'active'
     GROUP BY c.id
     ORDER BY c.name",
    [$userId]
);

// Active sessions - count students currently checked in
$activeSessions = $db->fetchAll(
    "SELECT s.*, u.first_name, u.last_name,
            ls.station_code,
            lf.name as floor_name
     FROM attendance_sessions s
     LEFT JOIN users u ON s.user_id = u.id
     LEFT JOIN lab_stations ls ON s.station_id = ls.id
     LEFT JOIN lab_floors lf ON s.floor_id = lf.id
     WHERE s.status = 'active'
     ORDER BY s.clock_in DESC",
    []
);

// Recent quizzes
$recentQuizzes = $db->fetchAll(
    "SELECT q.*, c.name as course_name,
            COUNT(qa.id) as attempt_count
     FROM quizzes q
     JOIN courses c ON q.course_id = c.id
     LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.status = 'completed'
     WHERE q.created_by = ?
     GROUP BY q.id
     ORDER BY q.created_at DESC
     LIMIT 5",
    [$userId]
);

// Lab stats
$labStats = $labService->getStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= asset('css/lab-floor.css') ?>" rel="stylesheet">
    <style>
        :root { --xp-primary: #6366f1; --xp-dark: #1e293b; --xp-radius: 0.75rem; }
        body { background: #f1f5f9; }
        .main-content { margin-left: 260px; padding: 2rem; }
        .stat-card { background: #fff; border-radius: var(--xp-radius); padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .course-card { background: #fff; border-radius: var(--xp-radius); padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: transform 0.2s; cursor: pointer; }
        .course-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .session-badge { position: absolute; top: -8px; right: -8px; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/admin_sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Welcome, <?= e($_SESSION['user_first_name'] ?? 'Teacher') ?>!</h2>
                <p class="text-muted mb-0">Manage your classes and monitor student progress.</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalStartSession">▶️ Start Session</button>
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCreateQuiz">❓ Create Quiz</button>
            </div>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon bg-primary bg-opacity-10 text-primary" style="width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">📚</div>
                        <div>
                            <div class="value fw-bold"><?= count($teacherCourses) ?></div>
                            <div class="label text-muted">My Courses</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon bg-success bg-opacity-10 text-success" style="width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">👨‍🎓</div>
                        <div>
                            <div class="value fw-bold"><?= array_sum(array_column($teacherCourses, 'student_count')) ?></div>
                            <div class="label text-muted">Total Students</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon bg-warning bg-opacity-10 text-warning" style="width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">🔴</div>
                        <div>
                            <div class="value fw-bold"><?= count($activeSessions) ?></div>
                            <div class="label text-muted">Active Sessions</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon bg-info bg-opacity-10 text-info" style="width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">🖥️</div>
                        <div>
                            <div class="value fw-bold"><?= $labStats['active'] ?>/<?= $labStats['total'] ?></div>
                            <div class="label text-muted">Stations Active</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Courses -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">📚 My Courses</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($teacherCourses as $course): ?>
                            <div class="col-md-6">
                                <div class="course-card position-relative">
                                    <h6 class="mb-1"><?= e($course['name']) ?></h6>
                                    <p class="text-muted mb-2 small"><?= e($course['code'] ?? '') ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-primary"><?= $course['student_count'] ?> students</span>
                                        <small class="text-muted"><?= $course['schedule'] ?? 'No schedule' ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($teacherCourses)): ?>
                            <div class="col-12 text-center text-muted py-4">
                                <p>No courses assigned yet.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Active Sessions -->
                <?php if (!empty($activeSessions)): ?>
                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">🔴 Active Sessions</h5>
                    </div>
                    <div class="card-body">
                        <?php $sessionIdx = 0; foreach ($activeSessions as $session): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 <?= $sessionIdx++ > 0 ? 'border-top' : '' ?>">
                            <div>
                                <strong><?= e($session['first_name'] . ' ' . $session['last_name']) ?></strong>
                                <div class="text-muted small">
                                    <?= e($session['station_code'] ?? 'No station') ?>
                                    <?php if (!empty($session['floor_name'])): ?> &mdash; <?= e($session['floor_name']) ?><?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success"><?= e($session['status']) ?></span>
                                <small class="text-muted">Since <?= e(date('M j, g:i A', strtotime($session['clock_in']))) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">⚡ Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="monitoring.php" class="btn btn-outline-primary">🖥️ Monitor Lab</a>
                            <a href="attendance_history.php" class="btn btn-outline-primary">📋 View Attendance</a>
                            <a href="quizzes_manage.php" class="btn btn-outline-primary">❓ Manage Quizzes</a>
                            <a href="submissions.php" class="btn btn-outline-primary">📤 Check Submissions</a>
                        </div>
                    </div>
                </div>

                <!-- Recent Quizzes -->
                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">❓ Recent Quizzes</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($recentQuizzes as $i => $quiz): ?>
                        <div class="d-flex justify-content-between align-items-center p-2 <?= $i > 0 ? 'border-top' : '' ?>">
                            <div>
                                <div class="fw-semibold small"><?= e($quiz['title']) ?></div>
                                <small class="text-muted"><?= e($quiz['course_name']) ?></small>
                            </div>
                            <span class="badge bg-info"><?= $quiz['attempt_count'] ?> attempts</span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($recentQuizzes)): ?>
                        <div class="text-center text-muted py-3 small">No quizzes yet</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Start Session Modal -->
    <div class="modal fade" id="modalStartSession" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="api/attendance/start-session.php" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">▶️ Start Lab Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Course *</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">Select course...</option>
                            <?php foreach ($teacherCourses as $course): ?>
                            <option value="<?= $course['id'] ?>"><?= e($course['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Session Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Auto-generated if empty">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">▶️ Start Session</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Quiz Modal -->
    <div class="modal fade" id="modalCreateQuiz" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="api/quizzes/create.php" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">❓ Create Quiz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Course *</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">Select course...</option>
                            <?php foreach ($teacherCourses as $course): ?>
                            <option value="<?= $course['id'] ?>"><?= e($course['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quiz Title *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Time Limit (seconds)</label>
                            <input type="number" name="time_limit_seconds" class="form-control" value="1800">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Max Attempts</label>
                            <input type="number" name="max_attempts" class="form-control" value="1" min="1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Quiz</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
