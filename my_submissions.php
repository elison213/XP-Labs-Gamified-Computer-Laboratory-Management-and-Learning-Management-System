<?php
/**
 * XPLabs - Student My Submissions Page
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::requireRole('student');

$userId = Auth::id();
$db = Database::getInstance();

// Get student's submissions
$submissions = $db->fetchAll(
    "SELECT s.*, a.title as assignment_title, a.max_points, a.due_date,
            c.name as course_name, c.code as course_code
     FROM submissions s
     JOIN assignments a ON s.assignment_id = a.id
     LEFT JOIN courses c ON a.course_id = c.id
     WHERE s.user_id = ?
     ORDER BY s.submitted_at DESC",
    [$userId]
);

// Count by status
$pendingCount = 0;
$gradedCount = 0;
$totalPoints = 0;
$gradedAssignments = 0;

foreach ($submissions as $s) {
    if ($s['status'] === 'graded') {
        $gradedCount++;
        $totalPoints += $s['score'] ?? 0;
        $gradedAssignments++;
    }
}

$averageGrade = $gradedAssignments > 0 ? round($totalPoints / $gradedAssignments, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Submissions - XPLabs</title>
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

        .submission-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem;
            transition: all 0.2s;
        }
        .submission-card:hover { border-color: var(--accent); }
        
        .status-pill {
            padding: 0.25rem 0.75rem; border-radius: 20px;
            font-size: 0.75rem; font-weight: 600;
        }
        .status-pill.submitted { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .status-pill.graded { background: rgba(34, 197, 94, 0.2); color: var(--green); }
        .status-pill.late { background: rgba(234, 179, 8, 0.2); color: var(--yellow); }

        .grade-badge {
            display: inline-block; padding: 0.25rem 0.5rem;
            border-radius: 4px; font-size: 0.85rem; font-weight: 700;
        }
        .grade-badge.high { background: rgba(34, 197, 94, 0.2); color: var(--green); }
        .grade-badge.medium { background: rgba(234, 179, 8, 0.2); color: var(--yellow); }
        .grade-badge.low { background: rgba(239, 68, 68, 0.2); color: var(--red); }

        .feedback-box {
            background: var(--bg-main); border: 1px solid var(--border);
            border-radius: 6px; padding: 0.75rem; margin-top: 0.5rem;
        }
        .text-muted { color: var(--text-muted) !important; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/student_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-upload me-2"></i>My Submissions</h2>
                <p class="text-muted mb-0">Track your submitted assignments and grades</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <div class="value"><?= count($submissions) ?></div>
                    <div class="label">Total Submitted</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <div class="value" style="color: var(--green)"><?= $gradedCount ?></div>
                    <div class="label">Graded</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <div class="value" style="color: var(--accent)"><?= $averageGrade ?></div>
                    <div class="label">Average Grade</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <div class="value" style="color: var(--yellow)"><?= $totalPoints ?></div>
                    <div class="label">Total Points</div>
                </div>
            </div>
        </div>

        <!-- Submissions List -->
        <?php if (empty($submissions)): ?>
        <div class="xp-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-upload fs-1 text-muted d-block mb-3"></i>
                <h4>No Submissions Yet</h4>
                <p class="text-muted">You haven't submitted any assignments yet</p>
                <a href="assignments.php" class="btn btn-primary">
                    <i class="bi bi-journal-text me-1"></i> View Assignments
                </a>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($submissions as $s): 
            $isLate = $s['is_late'] ?? 0;
            $statusClass = $s['status'] === 'graded' ? 'graded' : ($isLate ? 'late' : 'submitted');
            $statusText = $s['status'] === 'graded' ? 'Graded' : ($isLate ? 'Submitted Late' : 'Submitted');
            
            $gradePercent = $s['max_points'] > 0 ? (($s['score'] ?? 0) / $s['max_points']) * 100 : 0;
            $gradeClass = $gradePercent >= 80 ? 'high' : ($gradePercent >= 50 ? 'medium' : 'low');
        ?>
        <div class="submission-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="flex-grow-1">
                    <h5 class="mb-1"><?= e($s['assignment_title']) ?></h5>
                    <div class="text-muted small"><?= e($s['course_name'] ?? $s['course_code'] ?? '') ?></div>
                </div>
                <span class="status-pill <?= $statusClass ?>"><?= $statusText ?></span>
            </div>
            
            <div class="row g-2 mt-2">
                <div class="col-md-4">
                    <div class="text-muted small">Submitted</div>
                    <div><?= $s['submitted_at'] ? date('M j, Y g:i A', strtotime($s['submitted_at'])) : '—' ?></div>
                </div>
                <?php if ($s['status'] === 'graded'): ?>
                <div class="col-md-4">
                    <div class="text-muted small">Grade</div>
                    <div>
                        <span class="grade-badge <?= $gradeClass ?>">
                            <?= number_format($s['score'] ?? 0, 1) ?> / <?= $s['max_points'] ?>
                            (<?= number_format($gradePercent, 1) ?>%)
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($s['status'] === 'graded' && $s['feedback']): ?>
            <div class="feedback-box mt-2">
                <div class="text-muted small mb-1"><i class="bi bi-chat-text me-1"></i>Teacher Feedback</div>
                <div><?= nl2br(e($s['feedback'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>