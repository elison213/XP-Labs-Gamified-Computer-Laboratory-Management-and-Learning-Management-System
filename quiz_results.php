<?php
/**
 * XPLabs - Quiz Results (Teacher/Admin)
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::requireRole(['admin', 'teacher']);

$db = Database::getInstance();
$role = $_SESSION['user_role'];
$userId = Auth::id();
$quizId = (int) ($_GET['quiz_id'] ?? 0);

if (!$quizId) {
    header('Location: quizzes_manage.php');
    exit;
}

// Get quiz details
$quiz = $db->fetch(
    "SELECT q.*, c.name as course_name FROM quizzes q 
     LEFT JOIN courses c ON q.course_id = c.id WHERE q.id = ?",
    [$quizId]
);

if (!$quiz) {
    header('Location: quizzes_manage.php');
    exit;
}

// Get questions
$questions = $db->fetchAll(
    "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order ASC",
    [$quizId]
);

// Get attempts with student info
$attempts = $db->fetchAll(
    "SELECT qa.*, u.lrn, u.first_name, u.last_name
     FROM quiz_attempts qa
     JOIN users u ON qa.user_id = u.id
     WHERE qa.quiz_id = ?
     ORDER BY qa.score_percentage DESC, qa.finished_at DESC",
    [$quizId]
);

// Calculate stats
$totalAttempts = count($attempts);
$avgScore = $totalAttempts > 0 ? array_sum(array_column($attempts, 'score_percentage')) / $totalAttempts : 0;
$highScore = $totalAttempts > 0 ? max(array_column($attempts, 'score_percentage')) : 0;
$lowScore = $totalAttempts > 0 ? min(array_column($attempts, 'score_percentage')) : 0;
$passCount = count(array_filter($attempts, fn($a) => ($a['score_percentage'] ?? 0) >= 50));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - <?= e($quiz['title']) ?> - XPLabs</title>
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

        .sidebar { position: fixed; top: 0; left: 0; width: 260px; height: 100vh; background: var(--bg-card); border-right: 1px solid var(--border); z-index: 1000; overflow-y: auto; }
        .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .sidebar-brand h4 { margin: 0; font-weight: 700; color: #fff; }
        .sidebar-nav { padding: 1rem 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem; color: var(--text-muted); text-decoration: none; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(99, 102, 241, 0.1); color: var(--accent); }
        .sidebar-nav a i { width: 20px; text-align: center; }

        .main-content { margin-left: 260px; padding: 2rem; }

        .xp-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .xp-card .card-header { background: transparent; border-bottom: 1px solid var(--border); padding: 1rem 1.5rem; }
        .xp-card .card-header h5 { margin: 0; font-weight: 600; color: #fff; }
        .xp-card .card-body { padding: 1.5rem; }

        .xp-table { width: 100%; border-collapse: collapse; }
        .xp-table th, .xp-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        .xp-table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 600; }
        .xp-table tr:hover { background: rgba(99, 102, 241, 0.05); }

        .stat-card { background: var(--bg-dark); border: 1px solid var(--border); border-radius: 8px; padding: 1rem; text-align: center; }
        .stat-card .value { font-size: 1.5rem; font-weight: 700; color: #fff; }
        .stat-card .label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; }

        .score-bar {
            height: 8px;
            border-radius: 4px;
            background: var(--border);
            overflow: hidden;
            min-width: 80px;
        }
        .score-bar .fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }
        .score-bar .fill.high { background: var(--green); }
        .score-bar .fill.medium { background: var(--yellow); }
        .score-bar .fill.low { background: var(--red); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/admin_sidebar.php'; ?>
<div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-bar-chart me-2"></i>Quiz Results</h2>
                <p class="text-muted mb-0"><?= e($quiz['title']) ?><?= $quiz['course_name'] ? ' - ' . $quiz['course_name'] : '' ?></p>
            </div>
            <a href="quizzes_manage.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="value"><?= $totalAttempts ?></div>
                    <div class="label">Total Attempts</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="value text-primary"><?= number_format($avgScore, 1) ?>%</div>
                    <div class="label">Average Score</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="value text-success"><?= number_format($highScore, 1) ?>%</div>
                    <div class="label">Highest Score</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="value text-success"><?= $passCount ?> / <?= $totalAttempts ?></div>
                    <div class="label">Passed (≥50%)</div>
                </div>
            </div>
        </div>

        <!-- Quiz Info -->
        <div class="xp-card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Quiz Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3"><strong>Time Limit:</strong> <?= $quiz['time_limit_minutes'] ?> min</div>
                    <div class="col-md-3"><strong>Questions:</strong> <?= count($questions) ?></div>
                    <div class="col-md-3"><strong>Start:</strong> <?= $quiz['start_time'] ? date('M j, Y H:i', strtotime($quiz['start_time'])) : 'Not set' ?></div>
                    <div class="col-md-3"><strong>Status:</strong> <span class="badge bg-<?= $quiz['status'] === 'active' ? 'success' : ($quiz['status'] === 'draft' ? 'secondary' : 'danger') ?>"><?= ucfirst($quiz['status']) ?></span></div>
                </div>
            </div>
        </div>

        <!-- Attempts Table -->
        <div class="xp-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Student Attempts</h5>
                <span class="text-muted small"><?= $totalAttempts ?> attempts</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="xp-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Score</th>
                                <th>Correct</th>
                                <th>Duration</th>
                                <th>Finished</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attempts as $i => $a): 
                                $scoreClass = ($a['score_percentage'] ?? 0) >= 80 ? 'high' : (($a['score_percentage'] ?? 0) >= 50 ? 'medium' : 'low');
                                $durationMin = $a['started_at'] && $a['finished_at'] ? round((strtotime($a['finished_at']) - strtotime($a['started_at'])) / 60) : '-';
                            ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <div class="fw-semibold"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></div>
                                    <div class="text-muted small"><?= e($a['lrn']) ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="score-bar">
                                            <div class="fill <?= $scoreClass ?>" style="width: <?= $a['score_percentage'] ?? 0 ?>%"></div>
                                        </div>
                                        <span class="fw-bold <?= $scoreClass === 'high' ? 'text-success' : ($scoreClass === 'medium' ? 'text-warning' : 'text-danger') ?>">
                                            <?= number_format($a['score_percentage'] ?? 0, 1) ?>%
                                        </span>
                                    </div>
                                </td>
                                <td><?= $a['correct_answers'] ?> / <?= $a['total_questions'] ?></td>
                                <td><?= $durationMin ?> min</td>
                                <td><?= $a['finished_at'] ? date('M j, H:i', strtotime($a['finished_at'])) : '-' ?></td>
                                <td><span class="badge bg-<?= $a['status'] === 'completed' ? 'success' : 'warning' ?>"><?= ucfirst($a['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($attempts)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No attempts yet</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>