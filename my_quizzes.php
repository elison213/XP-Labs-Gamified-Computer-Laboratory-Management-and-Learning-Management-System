<?php
/**
 * XPLabs - Student My Quizzes Page
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::requireRole('student');

$userId = Auth::id();
$db = Database::getInstance();

// Get quizzes for courses the student is enrolled in (active/scheduled)
$availableQuizzes = $db->fetchAll(
    "SELECT q.*, c.name as course_name, c.code as course_code,
            u.first_name as teacher_first, u.last_name as teacher_last,
            qq.question_count,
            COALESCE(latest.best_score, 0) as best_score,
            latest.attempt_count,
            CASE 
                WHEN latest.attempts_remaining = 0 THEN 'maxed'
                WHEN q.scheduled_at > NOW() THEN 'upcoming'
                WHEN q.closes_at < NOW() THEN 'closed'
                ELSE 'available'
            END as status
     FROM quizzes q
     JOIN courses c ON q.course_id = c.id
     JOIN course_enrollments ce ON c.id = ce.course_id
     JOIN users u ON q.created_by = u.id
     LEFT JOIN (
         SELECT qa.quiz_id, qa.user_id,
                MAX(qa.score_percentage) as best_score,
                COUNT(*) as attempt_count,
                GREATEST(0, COALESCE(q.max_attempts, 1) - COUNT(*)) as attempts_remaining
         FROM quiz_attempts qa
         JOIN quizzes q ON qa.quiz_id = q.id
         WHERE qa.user_id = ?
         GROUP BY qa.quiz_id, qa.user_id
     ) latest ON q.id = latest.quiz_id AND latest.user_id = ?
     LEFT JOIN (
         SELECT quiz_id, COUNT(*) as question_count FROM quiz_questions GROUP BY quiz_id
     ) qq ON q.id = qq.quiz_id
     WHERE ce.user_id = ? AND q.status IN ('active', 'scheduled')
     ORDER BY 
         CASE 
             WHEN q.closes_at < NOW() THEN 4
             WHEN q.scheduled_at > NOW() THEN 2
             WHEN latest.attempts_remaining = 0 THEN 3
             ELSE 1
         END,
         q.closes_at ASC",
    [$userId, $userId, $userId]
);

// Count by status
$availableCount = 0;
$upcomingCount = 0;
$closedCount = 0;
$completedCount = 0;
$totalScored = 0;
$scoredCount = 0;

foreach ($availableQuizzes as $q) {
    if ($q['status'] === 'available') $availableCount++;
    elseif ($q['status'] === 'upcoming') $upcomingCount++;
    elseif ($q['status'] === 'closed') $closedCount++;
    if ($q['best_score'] > 0) {
        $totalScored += $q['best_score'];
        $scoredCount++;
    }
}
$avgScore = $scoredCount > 0 ? round($totalScored / $scoredCount, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Quizzes - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-main: #0f172a;
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
            background: var(--bg-main);
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

        .stat-box {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 8px; padding: 1rem; text-align: center;
        }
        .stat-box .value { font-size: 1.5rem; font-weight: 700; color: var(--text); }
        .stat-box .label { font-size: 0.75rem; color: var(--text-muted); }

        .quiz-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem;
            transition: all 0.2s;
        }
        .quiz-card:hover { border-color: var(--accent); box-shadow: 0 2px 8px rgba(99, 102, 241, 0.2); }
        
        .status-badge {
            padding: 0.25rem 0.75rem; border-radius: 20px;
            font-size: 0.75rem; font-weight: 600;
        }
        .status-badge.available { background: rgba(99, 102, 241, 0.2); color: var(--accent); }
        .status-badge.upcoming { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .status-badge.closed { background: rgba(100, 116, 139, 0.2); color: var(--text-muted); }
        .status-badge.maxed { background: rgba(34, 197, 94, 0.2); color: var(--green); }

        .course-label { font-size: 0.75rem; color: var(--text-muted); }
        .quiz-meta { font-size: 0.85rem; color: var(--text-muted); }
        .text-muted { color: var(--text-muted) !important; }
        .score-display { font-weight: 700; color: var(--green); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/student_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-question-circle me-2"></i>My Quizzes</h2>
                <p class="text-muted mb-0">Take quizzes and track your scores</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <div class="value" style="color: var(--accent)"><?= $availableCount ?></div>
                    <div class="label">Available</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <div class="value" style="color: #60a5fa"><?= $upcomingCount ?></div>
                    <div class="label">Upcoming</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <div class="value" style="color: var(--text-muted)"><?= $closedCount ?></div>
                    <div class="label">Closed</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <div class="value" style="color: var(--green)"><?= $avgScore ?>%</div>
                    <div class="label">Avg Score</div>
                </div>
            </div>
        </div>

        <!-- Quizzes List -->
        <?php if (empty($availableQuizzes)): ?>
        <div class="xp-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-question-circle fs-1 text-muted d-block mb-3"></i>
                <h4>No Quizzes Yet</h4>
                <p class="text-muted">Check back later for new quizzes from your teachers</p>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($availableQuizzes as $q): 
            $closesText = $q['closes_at'] ? date('M j, g:i A', strtotime($q['closes_at'])) : 'No deadline';
            $scheduledText = $q['scheduled_at'] ? date('M j, g:i A', strtotime($q['scheduled_at'])) : '';
            $attemptsLeft = $q['max_attempts'] - ($q['attempt_count'] ?? 0);
            $attemptsLeft = max(0, $attemptsLeft);
        ?>
        <div class="quiz-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="flex-grow-1">
                    <h5 class="mb-1"><?= e($q['title']) ?></h5>
                    <div class="course-label">
                        <?= e($q['course_name'] ?? $q['course_code']) ?> 
                        <span class="text-muted">· By <?= e($q['teacher_first'] . ' ' . substr($q['teacher_last'], 0, 1) . '.') ?></span>
                    </div>
                </div>
                <span class="status-badge <?= $q['status'] ?>"><?= ucfirst($q['status']) ?></span>
            </div>
            
            <?php if ($q['description']): ?>
            <p class="text-muted small mb-2"><?= nl2br(e($q['description'])) ?></p>
            <?php endif; ?>
            
            <div class="quiz-meta d-flex flex-wrap gap-3 mb-2">
                <span><i class="bi bi-clock me-1"></i><?= $q['time_limit_per_q'] ?? 30 ?>s per question</span>
                <span><i class="bi bi-list-ul me-1"></i><?= $q['question_count'] ?? 0 ?> questions</span>
                <span><i class="bi bi-lightning me-1"></i>Powerups: <?= $q['allow_powerups'] ? 'Allowed' : 'Disabled' ?></span>
                <span><i class="bi bi-bar-chart me-1"></i>Attempts: <?= $q['attempt_count'] ?? 0 ?>/<?= $q['max_attempts'] ?? '∞' ?></span>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="quiz-meta">
                    <i class="bi bi-calendar me-1"></i>Closes: <?= $closesText ?>
                    <?php if ($q['scheduled_at'] && strtotime($q['scheduled_at']) > time()): ?>
                    <span class="ms-2 badge bg-info">Starts <?= $scheduledText ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex gap-2 align-items-center">
                    <?php if ($q['best_score'] > 0): ?>
                    <span class="score-display"><i class="bi bi-trophy me-1"></i>Best: <?= number_format($q['best_score'], 1) ?>%</span>
                    <?php endif; ?>
                    
                    <?php if ($q['status'] === 'available' && $attemptsLeft > 0): ?>
                    <a href="quiz_take.php?quiz=<?= $q['id'] ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-play-fill me-1"></i> <?= $q['attempt_count'] ? 'Retry' : 'Start' ?>
                    </a>
                    <?php elseif ($q['status'] === 'upcoming'): ?>
                    <span class="btn btn-sm btn-outline-secondary disabled">Not yet open</span>
                    <?php elseif ($q['status'] === 'closed'): ?>
                    <span class="btn btn-sm btn-outline-secondary disabled">Closed</span>
                    <?php elseif ($attemptsLeft <= 0): ?>
                    <span class="btn btn-sm btn-outline-secondary disabled">No attempts left</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>