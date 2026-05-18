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

// Backward-compatible schema guard for environments where migration 042 has not run yet.
$hasShowResultsImmediately = (int) $db->fetchOne(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'quizzes'
       AND column_name = 'show_results_immediately'"
) > 0;
if (!$hasShowResultsImmediately) {
    $db->query("ALTER TABLE quizzes ADD COLUMN show_results_immediately TINYINT(1) DEFAULT 1 AFTER allow_powerups");
    $hasShowResultsImmediately = true;
}
$hasMaxAttempts = (int) $db->fetchOne(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'quizzes'
       AND column_name = 'max_attempts'"
) > 0;
if (!$hasMaxAttempts) {
    $db->query("ALTER TABLE quizzes ADD COLUMN max_attempts INT DEFAULT 1 AFTER time_limit_per_q");
    $hasMaxAttempts = true;
}

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
            case 'update_quiz':
                $quizId = (int) ($_POST['quiz_id'] ?? 0);
                $quiz = $db->fetch(
                    $role === 'teacher'
                        ? "SELECT * FROM quizzes WHERE id = ? AND created_by = ?"
                        : "SELECT * FROM quizzes WHERE id = ?",
                    $role === 'teacher' ? [$quizId, $userId] : [$quizId]
                );
                if (!$quiz) {
                    throw new \RuntimeException('Quiz not found or access denied.');
                }

                $questions = json_decode((string) ($_POST['questions_json'] ?? '[]'), true);
                $questions = is_array($questions) ? $questions : [];
                if (empty($questions)) {
                    throw new \RuntimeException('Please provide at least one valid question.');
                }

                $db->beginTransaction();
                $db->update('quizzes', [
                    'title' => trim($_POST['title'] ?? ''),
                    'description' => trim($_POST['description'] ?? '') ?: null,
                    'time_limit_per_q' => max(5, (int) ($_POST['time_limit_per_q'] ?? 30)),
                    'max_attempts' => max(1, (int) ($_POST['max_attempts'] ?? 1)),
                    'scheduled_at' => !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null,
                    'closes_at' => !empty($_POST['closes_at']) ? $_POST['closes_at'] : null,
                    'show_results_immediately' => isset($_POST['show_results_immediately']) ? 1 : 0,
                    'status' => $_POST['status'] ?? 'draft',
                ], 'id = ?', [$quizId]);

                $db->delete('quiz_questions', 'quiz_id = ?', [$quizId]);
                $number = 1;
                foreach ($questions as $q) {
                    $quizService->addQuestion($quizId, [
                        'question_number' => $number++,
                        'question_text' => $q['question_text'],
                        'type' => $q['type'],
                        'correct_answer' => $q['correct_answer'],
                        'points' => $q['points'],
                        'options' => $q['options'],
                    ]);
                }
                $db->commit();
                $message = ['type' => 'success', 'text' => 'Quiz updated successfully'];
                break;
        }
    } catch (\Exception $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->rollback();
        }
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}

// Filters
$courseFilter = (int) ($_GET['course_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';

// Get courses
$courses = $db->fetchAll("SELECT * FROM courses WHERE status != 'archived' ORDER BY name ASC");

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

$editingQuiz = null;
$editingQuestionsJson = '[]';
$editQuizId = (int) ($_GET['edit_quiz_id'] ?? 0);
if ($editQuizId > 0) {
    $editingQuiz = $db->fetch(
        $role === 'teacher'
            ? "SELECT * FROM quizzes WHERE id = ? AND created_by = ?"
            : "SELECT * FROM quizzes WHERE id = ?",
        $role === 'teacher' ? [$editQuizId, $userId] : [$editQuizId]
    );
    if ($editingQuiz) {
        $editQuestions = $db->fetchAll(
            "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_number ASC",
            [$editQuizId]
        );
        $rows = [];
        foreach ($editQuestions as $q) {
            $opts = json_decode((string) ($q['options'] ?? ''), true);
            $correctRaw = $q['correct_answer'];
            $correctDecoded = is_string($correctRaw) ? json_decode($correctRaw, true) : null;
            $rows[] = [
                'question_text' => $q['question_text'],
                'type' => $q['type'],
                'correct_answer' => (json_last_error() === JSON_ERROR_NONE) ? $correctDecoded : $correctRaw,
                'points' => (int) $q['points'],
                'options' => is_array($opts) ? $opts : null,
            ];
        }
        $editingQuestionsJson = json_encode($rows, JSON_UNESCAPED_UNICODE);
    }
}
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
            --bg-main: #f1f5f9;
            --bg-card: #ffffff;
            --border: #e2e8f0;
            --text: #1e293b;
            --text-muted: #64748b;
            --accent: #6366f1;
            --green: #22c55e;
            --yellow: #eab308;
            --red: #ef4444;
        }
        
        body {
            background: var(--bg-main);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }
        <?php if (($_SESSION['user_role'] ?? 'admin') === 'teacher'): ?>
        body { background: #f1f5f9 !important; }
        .main-content { background: #f1f5f9; }
        .xp-card { background: #fff; border-color: #e2e8f0; }
        .xp-card .card-header { background: #fff; border-color: #e2e8f0; }
        .xp-card .card-header h5 { color: #1e293b; }
        .xp-table th { color: #64748b; }
        .xp-table td { color: #1e293b; border-color: #e2e8f0; }
        .form-control, .form-select { background: #fff; border-color: #e2e8f0; color: #1e293b; }
        .form-label { color: #64748b; }
        .text-muted { color: #64748b !important; }
        .text-white { color: #1e293b !important; }
        .modal-content { background: #fff; }
        .modal-header { border-color: #e2e8f0; }
        .modal-footer { border-color: #e2e8f0; }
        .stat-card { background: #fff; border-color: #e2e8f0; }
        .stat-card .value { color: #1e293b; }
        .stat-card .label { color: #64748b; }
        <?php endif; ?>

        .sidebar {
            position: fixed; top: 0; left: 0; width: 260px; height: 100vh;
            background: var(--bg-card); border-right: 1px solid var(--border);
            z-index: 1000; overflow-y: auto;
        }
        .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .sidebar-brand h4 { margin: 0; font-weight: 700; color: var(--text); }
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
        .xp-card .card-header h5 { margin: 0; font-weight: 600; color: var(--text); }
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
            background: var(--bg-main); border: 1px solid var(--border); color: var(--text);
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
                            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>Archived</option>
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
                                <th>Per Question</th>
                                <th>Status</th>
                                <th>Schedule</th>
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
                                <td><?= (int) ($q['time_limit_per_q'] ?? 30) ?> sec</td>
                                <td><span class="status-pill <?= $q['status'] ?>"><?= ucfirst($q['status']) ?></span></td>
                                <td><?= $q['scheduled_at'] ? date('M j, H:i', strtotime($q['scheduled_at'])) : '-' ?></td>
                                <td class="text-end">
                                    <a href="quizzes_manage.php?edit_quiz_id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
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
            <form id="createQuizForm" class="modal-content">
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
                            <label class="form-label">Time Per Question (seconds)</label>
                            <input type="number" name="time_limit_per_q" class="form-control" value="30" min="5">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max Attempts</label>
                            <input type="number" name="max_attempts" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Time</label>
                            <input type="datetime-local" name="scheduled_at" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Time</label>
                            <input type="datetime-local" name="closes_at" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="draft" selected>Draft</option>
                                <option value="active">Active</option>
                                <option value="scheduled">Scheduled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4 pt-1">
                                <input class="form-check-input" type="checkbox" id="create-show-results" name="show_results_immediately" value="1" checked>
                                <label class="form-check-label" for="create-show-results">
                                    Show results immediately
                                </label>
                            </div>
                        </div>
                        <div class="col-12 mt-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label mb-0">Questions</label>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addQuestionBtn">
                                    <i class="bi bi-plus-lg me-1"></i>Add Question
                                </button>
                            </div>
                            <div class="text-muted small mb-2">Supports multiple choice, true/false, short answer, code completion, and output prediction.</div>
                            <div id="questionList" class="d-flex flex-column gap-3"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="createQuizSubmitBtn">Create Quiz</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Quiz Modal -->
    <div class="modal fade" id="modalEditQuiz" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST" id="editQuizForm" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_quiz">
                <input type="hidden" name="quiz_id" value="<?= (int) ($editingQuiz['id'] ?? 0) ?>">
                <input type="hidden" name="questions_json" id="edit-questions-json" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Quiz</h5>
                    <a href="quizzes_manage.php" class="btn-close"></a>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Quiz Title *</label>
                            <input type="text" name="title" class="form-control" required value="<?= e($editingQuiz['title'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Time Per Question (seconds)</label>
                            <input type="number" name="time_limit_per_q" class="form-control" min="5" value="<?= (int) ($editingQuiz['time_limit_per_q'] ?? 30) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Max Attempts</label>
                            <input type="number" name="max_attempts" class="form-control" min="1" value="<?= (int) ($editingQuiz['max_attempts'] ?? 1) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Time</label>
                            <input type="datetime-local" name="scheduled_at" class="form-control" value="<?= !empty($editingQuiz['scheduled_at']) ? date('Y-m-d\TH:i', strtotime($editingQuiz['scheduled_at'])) : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time</label>
                            <input type="datetime-local" name="closes_at" class="form-control" value="<?= !empty($editingQuiz['closes_at']) ? date('Y-m-d\TH:i', strtotime($editingQuiz['closes_at'])) : '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <?php $statuses = ['draft', 'scheduled', 'active', 'completed', 'archived']; ?>
                                <?php foreach ($statuses as $s): ?>
                                <option value="<?= $s ?>" <?= (($editingQuiz['status'] ?? '') === $s) ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <div class="form-check mt-4 pt-1">
                                <input class="form-check-input" type="checkbox" id="edit-show-results" name="show_results_immediately" value="1" <?= ((int) ($editingQuiz['show_results_immediately'] ?? 1) === 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="edit-show-results">
                                    Show results immediately to students
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"><?= e($editingQuiz['description'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12 mt-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label mb-0">Questions</label>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="editAddQuestionBtn">
                                    <i class="bi bi-plus-lg me-1"></i>Add Question
                                </button>
                            </div>
                            <div class="text-muted small mb-2">Use the same question builder UI as Create Quiz.</div>
                            <div id="editQuestionList" class="d-flex flex-column gap-3"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="quizzes_manage.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Quiz</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function () {
        const csrfToken = <?= json_encode(csrf_token()) ?>;
        const form = document.getElementById('createQuizForm');
        const questionList = document.getElementById('questionList');
        const addQuestionBtn = document.getElementById('addQuestionBtn');
        const submitBtn = document.getElementById('createQuizSubmitBtn');
        const modalEl = document.getElementById('modalQuiz');

        function buildAnswerFields(type, card) {
            const container = card.querySelector('.answer-config');
            if (type === 'multiple_choice') {
                container.innerHTML = `
                    <div class="row g-2">
                        <div class="col-md-6"><input class="form-control option-input" placeholder="Option A"></div>
                        <div class="col-md-6"><input class="form-control option-input" placeholder="Option B"></div>
                        <div class="col-md-6"><input class="form-control option-input" placeholder="Option C"></div>
                        <div class="col-md-6"><input class="form-control option-input" placeholder="Option D"></div>
                        <div class="col-md-6">
                            <label class="form-label small">Correct Option</label>
                            <select class="form-select correct-option">
                                <option value="0">A</option>
                                <option value="1">B</option>
                                <option value="2">C</option>
                                <option value="3">D</option>
                            </select>
                        </div>
                    </div>`;
                return;
            }
            if (type === 'true_false') {
                container.innerHTML = `
                    <label class="form-label small">Correct Answer</label>
                    <select class="form-select correct-bool">
                        <option value="true">True</option>
                        <option value="false">False</option>
                    </select>`;
                return;
            }
            container.innerHTML = `
                <label class="form-label small">Correct Answer</label>
                <input type="text" class="form-control correct-text" placeholder="Enter correct answer">`;
        }

        function addQuestionCard() {
            const idx = questionList.children.length + 1;
            const card = document.createElement('div');
            card.className = 'border rounded p-3';
            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Question ${idx}</strong>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-question">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Question Text</label>
                    <textarea class="form-control question-text" rows="2" required></textarea>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label small">Question Type</label>
                        <select class="form-select question-type">
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="true_false">True / False</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="code_completion">Code Completion</option>
                            <option value="output_prediction">Output Prediction</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Points</label>
                        <input type="number" class="form-control question-points" min="1" value="10">
                    </div>
                </div>
                <div class="answer-config"></div>`;

            questionList.appendChild(card);
            buildAnswerFields('multiple_choice', card);

            card.querySelector('.question-type').addEventListener('change', function () {
                buildAnswerFields(this.value, card);
            });
            card.querySelector('.remove-question').addEventListener('click', function () {
                card.remove();
                [...questionList.children].forEach((item, i) => {
                    item.querySelector('strong').textContent = `Question ${i + 1}`;
                });
            });
        }

        addQuestionBtn.addEventListener('click', addQuestionCard);
        addQuestionCard();

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating...';

            const payload = {
                title: form.title.value.trim(),
                course_id: parseInt(form.course_id.value, 10),
                description: form.description.value.trim(),
                time_limit_per_q: parseInt(form.time_limit_per_q.value || '30', 10),
                max_attempts: parseInt(form.max_attempts.value || '1', 10),
                scheduled_at: form.scheduled_at.value || null,
                closes_at: form.closes_at.value || null,
                shuffle_questions: 0,
                shuffle_answers: 1,
                show_live_leaderboard: 1,
                allow_powerups: 1,
                show_results_immediately: form.show_results_immediately?.checked ? 1 : 0,
                publish: form.status.value === 'active',
                questions: []
            };

            for (const card of questionList.children) {
                const type = card.querySelector('.question-type').value;
                const question = {
                    question_text: card.querySelector('.question-text').value.trim(),
                    type: type,
                    points: parseInt(card.querySelector('.question-points').value || '10', 10)
                };
                if (!question.question_text) continue;

                if (type === 'multiple_choice') {
                    const options = [...card.querySelectorAll('.option-input')].map(el => el.value.trim()).filter(Boolean);
                    if (options.length < 2) continue;
                    const correctIdx = parseInt(card.querySelector('.correct-option').value, 10);
                    question.options = options;
                    question.correct_answer = options[correctIdx] ?? options[0];
                } else if (type === 'true_false') {
                    question.options = ['true', 'false'];
                    question.correct_answer = card.querySelector('.correct-bool').value;
                } else {
                    question.correct_answer = card.querySelector('.correct-text')?.value.trim() || '';
                }

                payload.questions.push(question);
            }

            if (!payload.title || !payload.course_id || payload.questions.length === 0) {
                alert('Please provide quiz title, course, and at least one valid question.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Quiz';
                return;
            }

            try {
                const resp = await fetch('api/quizzes/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify(payload)
                });
                const text = await resp.text();
                let result = {};
                try {
                    result = text ? JSON.parse(text) : {};
                } catch (parseErr) {
                    result = { error: text || 'Invalid server response' };
                }
                if (resp.ok && result.success) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    window.location.reload();
                } else {
                    alert(result.error || 'Failed to create quiz');
                }
            } catch (err) {
                alert('Network error while creating quiz');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Quiz';
            }
        });

        // Edit builder (matches Create UI)
        const editForm = document.getElementById('editQuizForm');
        const editQuestionList = document.getElementById('editQuestionList');
        const editAddQuestionBtn = document.getElementById('editAddQuestionBtn');
        const editQuestionsInput = document.getElementById('edit-questions-json');
        const editSeedQuestions = <?= $editingQuestionsJson ?: '[]' ?>;

        function addEditQuestionCard(seed = null) {
            if (!editQuestionList) return;
            const idx = editQuestionList.children.length + 1;
            const card = document.createElement('div');
            const seedType = seed?.type || 'multiple_choice';
            card.className = 'border rounded p-3';
            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Question ${idx}</strong>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-question">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Question Text</label>
                    <textarea class="form-control question-text" rows="2" required>${seed?.question_text ?? ''}</textarea>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label small">Question Type</label>
                        <select class="form-select question-type">
                            <option value="multiple_choice" ${seedType === 'multiple_choice' ? 'selected' : ''}>Multiple Choice</option>
                            <option value="true_false" ${seedType === 'true_false' ? 'selected' : ''}>True / False</option>
                            <option value="short_answer" ${seedType === 'short_answer' ? 'selected' : ''}>Short Answer</option>
                            <option value="code_completion" ${seedType === 'code_completion' ? 'selected' : ''}>Code Completion</option>
                            <option value="output_prediction" ${seedType === 'output_prediction' ? 'selected' : ''}>Output Prediction</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Points</label>
                        <input type="number" class="form-control question-points" min="1" value="${seed?.points ?? 10}">
                    </div>
                </div>
                <div class="answer-config"></div>`;
            editQuestionList.appendChild(card);

            buildAnswerFields(seedType, card);
            if (seedType === 'multiple_choice' && Array.isArray(seed?.options)) {
                const optionInputs = [...card.querySelectorAll('.option-input')];
                seed.options.slice(0, 4).forEach((opt, i) => { if (optionInputs[i]) optionInputs[i].value = opt; });
                const matchIdx = seed.options.findIndex(o => String(o) === String(seed?.correct_answer));
                if (matchIdx >= 0) card.querySelector('.correct-option').value = String(matchIdx);
            } else if (seedType === 'true_false') {
                card.querySelector('.correct-bool').value = String(seed?.correct_answer ?? 'true');
            } else {
                const input = card.querySelector('.correct-text');
                if (input) input.value = seed?.correct_answer ?? '';
            }

            card.querySelector('.question-type').addEventListener('change', function () {
                buildAnswerFields(this.value, card);
            });
            card.querySelector('.remove-question').addEventListener('click', function () {
                card.remove();
                [...editQuestionList.children].forEach((item, i) => {
                    item.querySelector('strong').textContent = `Question ${i + 1}`;
                });
            });
        }

        function collectQuestions(targetList) {
            const out = [];
            if (!targetList) return out;
            for (const card of targetList.children) {
                const type = card.querySelector('.question-type').value;
                const question = {
                    question_text: card.querySelector('.question-text').value.trim(),
                    type: type,
                    points: parseInt(card.querySelector('.question-points').value || '10', 10)
                };
                if (!question.question_text) continue;
                if (type === 'multiple_choice') {
                    const options = [...card.querySelectorAll('.option-input')].map(el => el.value.trim()).filter(Boolean);
                    if (options.length < 2) continue;
                    const correctIdx = parseInt(card.querySelector('.correct-option').value, 10);
                    question.options = options;
                    question.correct_answer = options[correctIdx] ?? options[0];
                } else if (type === 'true_false') {
                    question.options = ['true', 'false'];
                    question.correct_answer = card.querySelector('.correct-bool').value;
                } else {
                    question.correct_answer = card.querySelector('.correct-text')?.value.trim() || '';
                }
                out.push(question);
            }
            return out;
        }

        if (editAddQuestionBtn) {
            editAddQuestionBtn.addEventListener('click', () => addEditQuestionCard());
        }
        if (editQuestionList) {
            if (Array.isArray(editSeedQuestions) && editSeedQuestions.length > 0) {
                editSeedQuestions.forEach(q => addEditQuestionCard(q));
            } else if (editForm) {
                addEditQuestionCard();
            }
        }
        if (editForm) {
            editForm.addEventListener('submit', function (e) {
                const questions = collectQuestions(editQuestionList);
                if (questions.length === 0) {
                    e.preventDefault();
                    alert('Please add at least one valid question.');
                    return;
                }
                editQuestionsInput.value = JSON.stringify(questions);
            });
        }
    })();

    <?php if ($editingQuiz): ?>
    new bootstrap.Modal(document.getElementById('modalEditQuiz')).show();
    <?php endif; ?>
    </script>
</body>
</html>
