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
    "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_number ASC",
    [$quizId]
);

// Get attempts with student info
$attempts = $db->fetchAll(
    "SELECT qa.*, u.lrn, u.first_name, u.last_name,
            CASE 
                WHEN qa.max_score > 0 THEN ROUND((qa.total_score / qa.max_score) * 100, 2)
                ELSE 0
            END AS score_percentage
     FROM quiz_attempts qa
     JOIN users u ON qa.user_id = u.id
     WHERE qa.quiz_id = ?
     ORDER BY score_percentage DESC, qa.finished_at DESC",
    [$quizId]
);

$questionAnalytics = $db->fetchAll(
    "SELECT qq.id, qq.question_number, qq.question_text, qq.type,
            COUNT(qa.id) AS total_answers,
            SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) AS correct_answers,
            SUM(CASE WHEN qa.is_correct = 0 THEN 1 ELSE 0 END) AS wrong_answers
     FROM quiz_questions qq
     LEFT JOIN quiz_answers qa ON qa.question_id = qq.id
     WHERE qq.quiz_id = ?
     GROUP BY qq.id
     ORDER BY qq.question_number ASC",
    [$quizId]
);

$attemptIds = array_map(fn($a) => (int) $a['id'], $attempts);
$answersByAttempt = [];
if (!empty($attemptIds)) {
    $idList = implode(',', $attemptIds);
    $answers = $db->fetchAll(
        "SELECT qa.attempt_id, qa.answer, qa.is_correct, qa.points_earned,
                qq.question_number, qq.question_text, qq.correct_answer
         FROM quiz_answers qa
         JOIN quiz_questions qq ON qa.question_id = qq.id
         WHERE qa.attempt_id IN ($idList)
         ORDER BY qq.question_number ASC"
    );
    foreach ($answers as $ans) {
        $answersByAttempt[(int) $ans['attempt_id']][] = $ans;
    }
}

// Calculate stats
$totalAttempts = count($attempts);
$avgScore = $totalAttempts > 0 ? array_sum(array_column($attempts, 'score_percentage')) / $totalAttempts : 0;
$highScore = $totalAttempts > 0 ? max(array_column($attempts, 'score_percentage')) : 0;
$lowScore = $totalAttempts > 0 ? min(array_column($attempts, 'score_percentage')) : 0;
$passCount = count(array_filter($attempts, fn($a) => ($a['score_percentage'] ?? 0) >= 50));
$completionRate = $totalAttempts > 0
    ? round((count(array_filter($attempts, fn($a) => $a['status'] === 'completed')) / $totalAttempts) * 100, 1)
    : 0;

$distribution = [
    '90-100' => 0,
    '75-89' => 0,
    '50-74' => 0,
    '0-49' => 0,
];
foreach ($attempts as $a) {
    $score = (float) ($a['score_percentage'] ?? 0);
    if ($score >= 90) $distribution['90-100']++;
    elseif ($score >= 75) $distribution['75-89']++;
    elseif ($score >= 50) $distribution['50-74']++;
    else $distribution['0-49']++;
}
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

        .sidebar { position: fixed; top: 0; left: 0; width: 260px; height: 100vh; background: var(--bg-card); border-right: 1px solid var(--border); z-index: 1000; overflow-y: auto; }
        .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .sidebar-brand h4 { margin: 0; font-weight: 700; color: var(--text); }
        .sidebar-nav { padding: 1rem 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem; color: var(--text-muted); text-decoration: none; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(99, 102, 241, 0.1); color: var(--accent); }
        .sidebar-nav a i { width: 20px; text-align: center; }

        .main-content { margin-left: 260px; padding: 2rem; }

        .xp-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .xp-card .card-header { background: transparent; border-bottom: 1px solid var(--border); padding: 1rem 1.5rem; }
        .xp-card .card-header h5 { margin: 0; font-weight: 600; color: var(--text); }
        .xp-card .card-body { padding: 1.5rem; }

        .xp-table { width: 100%; border-collapse: collapse; }
        .xp-table th, .xp-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        .xp-table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 600; }
        .xp-table tr:hover { background: rgba(99, 102, 241, 0.05); }

        .stat-card { background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; padding: 1rem; text-align: center; }
        .stat-card .value { font-size: 1.5rem; font-weight: 700; color: var(--text); }
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
        .dist-bar { height: 10px; border-radius: 6px; background: #e2e8f0; overflow: hidden; }
        .dist-fill { height: 100%; background: var(--accent); }
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
            <div class="d-flex gap-2">
                <a href="quizzes_manage.php?edit_quiz_id=<?= (int) $quiz['id'] ?>" class="btn btn-primary">
                    <i class="bi bi-pencil-square me-1"></i> Edit Quiz
                </a>
                <a href="quizzes_manage.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
            </div>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="value"><?= $totalAttempts ?></div>
                    <div class="label">Total Attempts</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="value text-primary"><?= number_format($avgScore, 1) ?>%</div>
                    <div class="label">Average Score</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="value text-success"><?= number_format($highScore, 1) ?>%</div>
                    <div class="label">Highest Score</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="value text-danger"><?= number_format($lowScore, 1) ?>%</div>
                    <div class="label">Lowest Score</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="value text-success"><?= $passCount ?> / <?= $totalAttempts ?></div>
                    <div class="label">Passed (≥50%)</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="value text-info"><?= number_format($completionRate, 1) ?>%</div>
                    <div class="label">Completion</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="xp-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Quiz Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-2"><strong>Time/Question:</strong> <?= (int) ($quiz['time_limit_per_q'] ?? 30) ?> sec</div>
                            <div class="col-md-6 mb-2"><strong>Questions:</strong> <?= count($questions) ?></div>
                            <div class="col-md-6 mb-2"><strong>Start:</strong> <?= $quiz['scheduled_at'] ? date('M j, Y H:i', strtotime($quiz['scheduled_at'])) : 'Not set' ?></div>
                            <div class="col-md-6 mb-2"><strong>End:</strong> <?= $quiz['closes_at'] ? date('M j, Y H:i', strtotime($quiz['closes_at'])) : 'Not set' ?></div>
                            <div class="col-md-12"><strong>Status:</strong> <span class="badge bg-<?= $quiz['status'] === 'active' ? 'success' : ($quiz['status'] === 'draft' ? 'secondary' : 'danger') ?>"><?= ucfirst($quiz['status']) ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="xp-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>Score Distribution</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($distribution as $label => $count): 
                            $pct = $totalAttempts > 0 ? round(($count / $totalAttempts) * 100, 1) : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span><?= $label ?>%</span>
                                <span><?= $count ?> students (<?= $pct ?>%)</span>
                            </div>
                            <div class="dist-bar"><div class="dist-fill" style="width: <?= $pct ?>%"></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
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
                                <th class="text-end">Review</th>
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
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#attempt-<?= (int) $a['id'] ?>">
                                        View Answers
                                    </button>
                                </td>
                            </tr>
                            <tr class="collapse" id="attempt-<?= (int) $a['id'] ?>">
                                <td colspan="8" class="bg-light">
                                    <?php $attemptAnswers = $answersByAttempt[(int) $a['id']] ?? []; ?>
                                    <?php if (empty($attemptAnswers)): ?>
                                        <div class="text-muted">No submitted answers.</div>
                                    <?php else: ?>
                                        <?php foreach ($attemptAnswers as $ans): 
                                            $studentAnsRaw = $ans['answer'];
                                            $studentAnsJson = json_decode((string) $studentAnsRaw, true);
                                            $studentAns = (json_last_error() === JSON_ERROR_NONE && $studentAnsJson !== null)
                                                ? (is_array($studentAnsJson) ? implode(', ', $studentAnsJson) : (string) $studentAnsJson)
                                                : (string) $studentAnsRaw;
                                            $correctRaw = $ans['correct_answer'];
                                            $correctJson = json_decode((string) $correctRaw, true);
                                            $correctAns = (json_last_error() === JSON_ERROR_NONE && $correctJson !== null)
                                                ? (is_array($correctJson) ? implode(', ', $correctJson) : (string) $correctJson)
                                                : (string) $correctRaw;
                                        ?>
                                        <div class="border rounded p-2 mb-2 bg-white">
                                            <div class="small fw-semibold mb-1">Q<?= (int) $ans['question_number'] ?>: <?= e($ans['question_text']) ?></div>
                                            <div class="small"><strong>Student:</strong> <span class="<?= ((int) $ans['is_correct'] === 1) ? 'text-success' : 'text-danger' ?>"><?= e($studentAns) ?></span></div>
                                            <div class="small"><strong>Correct:</strong> <?= e($correctAns) ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($attempts)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No attempts yet</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Question Analytics -->
        <div class="xp-card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-activity me-2"></i>Question Analytics (Right vs Wrong)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="xp-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Question</th>
                                <th>Type</th>
                                <th>Right</th>
                                <th>Wrong</th>
                                <th>Accuracy</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($questionAnalytics as $qa): 
                                $total = (int) ($qa['total_answers'] ?? 0);
                                $right = (int) ($qa['correct_answers'] ?? 0);
                                $wrong = (int) ($qa['wrong_answers'] ?? 0);
                                $acc = $total > 0 ? round(($right / $total) * 100, 1) : 0;
                                $accClass = $acc >= 75 ? 'text-success' : ($acc >= 50 ? 'text-warning' : 'text-danger');
                            ?>
                            <tr>
                                <td><?= (int) $qa['question_number'] ?></td>
                                <td><?= e(mb_strimwidth($qa['question_text'], 0, 80, '...')) ?></td>
                                <td><?= e($qa['type']) ?></td>
                                <td><span class="badge bg-success"><?= $right ?></span></td>
                                <td><span class="badge bg-danger"><?= $wrong ?></span></td>
                                <td><strong class="<?= $accClass ?>"><?= number_format($acc, 1) ?>%</strong></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($questionAnalytics)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No question analytics yet</td></tr>
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
