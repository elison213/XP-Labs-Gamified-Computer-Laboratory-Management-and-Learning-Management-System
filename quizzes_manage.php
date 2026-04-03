<?php
/**
 * XPLabs - Quiz Management (Teacher/Admin)
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\QuizService;
use XPLabs\Lib\Database;

Auth::requireRole(['admin', 'teacher']);

$db = Database::getInstance();
$role = $_SESSION['user_role'];
$userId = Auth::id();
$quizService = new QuizService();

// Handle POST actions
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'delete':
                $quizId = (int) $_POST['quiz_id'];
                $db->delete('quiz_questions', 'quiz_id = ?', [$quizId]);
                $db->delete('quizzes', 'id = ?', [$quizId]);
                $message = ['type' => 'success', 'text' => 'Quiz deleted successfully'];
                break;
        }
    } catch (\Exception $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}

// Filters
$courseFilter = (int) ($_GET['course_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';

// Get courses
$courses = $db->fetchAll("SELECT * FROM courses WHERE is_active = 1 ORDER BY name ASC");

// Get quizzes
$where = ['1=1'];
$params = [];

if ($courseFilter) {
    $where[] = 'q.course_id = ?';
    $params[] = $courseFilter;
}
if ($statusFilter) {
    $where[] = 'q.status = ?';
    $params[] = $statusFilter;
}

// For teachers, only show their quizzes
if ($role === 'teacher') {
    $where[] = 'q.created_by = ?';
    $params[] = $userId;
}

$whereClause = implode(' AND ', $where);

$quizzes = $db->fetchAll(
    "SELECT q.*, c.name as course_name,
            COUNT(DISTINCT qq.id) as question_count,
            COUNT(DISTINCT qa.id) as attempt_count
     FROM quizzes q
     LEFT JOIN courses c ON q.course_id = c.id
     LEFT JOIN quiz_questions qq ON qq.quiz_id = q.id
     LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id AND qa.status = 'completed'
     WHERE $whereClause
     GROUP BY q.id
     ORDER BY q.created_at DESC",
    $params
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Management - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --border: #334155;
            --text: #e2e8f0;
            --text-muted: #94a3b8;
            --accent: #6366f1;
            --green: #22c55e;
            --yellow: #eab308;
            --red: #ef4444;
        }
        
        body {
            background: var(--bg-dark);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }

        .sidebar {
            position: fixed; top: 0; left: 0; width: 260px; height: 100vh;
            background: var(--bg-card); border-right: 1px solid var(--border);
            z-index: 1000; overflow-y: auto;
        }
        .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .sidebar-brand h4 { margin: 0; font-weight: 700; color: #fff; }
        .sidebar-brand small { color: var(--text-muted); }
        .sidebar-nav { padding: 1rem 0; }
        .sidebar-nav a {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.75rem 1.5rem; color: var(--text-muted);
            text-decoration: none; transition: all 0.2s;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: rgba(99, 102, 241, 0.1); color: var(--accent);
        }
        .sidebar-nav a i { width: 20px; text-align: center; }
        .sidebar-nav .nav-section {
            padding: 0.5rem 1.5rem; font-size: 0.7rem;
            text-transform: uppercase; letter-spacing: 0.05em;
            color: var(--text-muted); margin-top: 0.5rem;
        }

        .main-content { margin-left: 260px; padding: 2rem; }

        .xp-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 12px; overflow: hidden;
        }
        .xp-card .card-header {
            background: transparent; border-bottom: 1px solid var(--border);
            padding: 1rem 1.5rem;
        }
        .xp-card .card-header h5 { margin: 0; font-weight: 600; color: #fff; }
        .xp-card .card-body { padding: 1.5rem; }

        .xp-table { width: 100%; border-collapse: collapse; }
        .xp-table th, .xp-table td {
            padding: 0.75rem 1rem; text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .xp-table th {
            font-size: 0.75rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--text-muted);
            font-weight: 600;
        }
        .xp-table tr:hover { background: rgba(99, 102, 241, 0.05); }

        .form-control, .form-select {
            background: var(--bg-dark); border: 1px solid var(--border); color: var(--text);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent); box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }
        .form-label { color: var(--text-muted); font-size: 0.85rem; }

        .status-pill {
            display: inline-block; padding: 0.25rem 0.75rem;
            border-radius: 20px; font-size: 0.7rem; font-weight: 600;
        }
        .status-pill.draft { background: rgba(100, 116, 139, 0.1); color: var(--text-muted); }
        .status-pill.active { background: rgba(34, 197, 94, 0.1); color: var(--green); }
        .status-pill.closed { background: rgba(239, 68, 68, 0.1); color: var(--red); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/admin_sidebar.php'; ?>
<div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-question-circle me-2"></i>Quiz Management</h2>
                <p class="text-muted mb-0">Create and manage quizzes</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalQuiz">
                <i class="bi bi-plus-lg me-1"></i> Create Quiz
            </button>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show">
            <?= e($message['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="xp-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-3">
                        <select name="course_id" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $courseFilter == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="quizzes_manage.php" class="btn btn-outline-secondary w-100">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quizzes Table -->
        <div class="xp-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Quizzes</h5>
                <span class="text-muted small"><?= count($quizzes) ?> quizzes</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="xp-table">
                        <thead>
                            <tr>
                                <th>Quiz Title</th>
                                <th>Course</th>
                                <th>Questions</th>
                                <th>Attempts</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Start Time</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quizzes as $q): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($q['title']) ?></div>
                                    <div class="text-muted small"><?= $q['description'] ? e(substr($q['description'], 0, 50)) . '...' : '' ?></div>
                                </td>
                                <td><?= e($q['course_name'] ?? '-') ?></td>
                                <td><?= $q['question_count'] ?></td>
                                <td><?= $q['attempt_count'] ?></td>
                                <td><?= $q['time_limit_minutes'] ?> min</td>
                                <td><span class="status-pill <?= $q['status'] ?>"><?= ucfirst($q['status']) ?></span></td>
                                <td><?= $q['start_time'] ? date('M j, H:i', strtotime($q['start_time'])) : '-' ?></td>
                                <td class="text-end">
                                    <a href="quiz_results.php?quiz_id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Results">
                                        <i class="bi bi-bar-chart"></i>
                                    </a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this quiz?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="quiz_id" value="<?= $q['id'] ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($quizzes)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No quizzes found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Quiz Modal -->
    <div class="modal fade" id="modalQuiz" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form action="api/quizzes/create.php" method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Quiz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Quiz Title *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Course *</label>
                            <select name="course_id" class="form-select" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Time Limit (minutes)</label>
                            <input type="number" name="time_limit_minutes" class="form-control" value="30" min="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Time</label>
                            <input type="datetime-local" name="start_time" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Time</label>
                            <input type="datetime-local" name="end_time" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="draft" selected>Draft</option>
                                <option value="active">Active</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Questions (one per line, format: Question|A|B|C|D|correct_letter)</label>
                            <textarea name="questions" class="form-control" rows="6" placeholder="What is 2+2?|3|4|5|6|B&#10;What is PHP?|Language|Framework|Database|Server|A"></textarea>
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