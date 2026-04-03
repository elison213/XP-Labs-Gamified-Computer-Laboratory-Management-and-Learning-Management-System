<?php
/**
 * XPLabs - Student Assignments Page
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::requireRole('student');

$userId = Auth::id();
$db = Database::getInstance();

// Get assignments for courses the student is enrolled in
$assignments = $db->fetchAll(
    "SELECT a.*, c.name as course_name, c.code as course_code,
            s.id as submission_id, s.status as submission_status, s.score as grade, s.submitted_at,
            CASE 
                WHEN s.id IS NOT NULL THEN 'submitted'
                WHEN a.due_date < NOW() THEN 'overdue'
                ELSE 'pending'
            END as status
     FROM assignments a
     JOIN courses c ON a.course_id = c.id
     JOIN course_enrollments ce ON c.id = ce.course_id
     LEFT JOIN submissions s ON a.id = s.assignment_id AND s.user_id = ?
     WHERE ce.user_id = ? AND a.status = 'published'
     ORDER BY 
         CASE 
             WHEN s.id IS NOT NULL THEN 3
             WHEN a.due_date < NOW() THEN 2
             ELSE 1
         END,
         a.due_date ASC",
    [$userId, $userId]
);

// Count by status
$pendingCount = 0;
$submittedCount = 0;
$gradedCount = 0;
$overdueCount = 0;

foreach ($assignments as $a) {
    if ($a['submission_status'] === 'graded') $gradedCount++;
    elseif ($a['submission_id']) $submittedCount++;
    elseif ($a['status'] === 'overdue') $overdueCount++;
    else $pendingCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - XPLabs</title>
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

        .assignment-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem;
            transition: all 0.2s;
        }
        .assignment-card:hover { border-color: var(--accent); box-shadow: 0 2px 8px rgba(99, 102, 241, 0.2); }
        
        .status-badge {
            padding: 0.25rem 0.75rem; border-radius: 20px;
            font-size: 0.75rem; font-weight: 600;
        }
        .status-badge.pending { background: rgba(99, 102, 241, 0.2); color: var(--accent); }
        .status-badge.submitted { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .status-badge.graded { background: rgba(34, 197, 94, 0.2); color: var(--green); }
        .status-badge.overdue { background: rgba(239, 68, 68, 0.2); color: var(--red); }

        .due-date { font-size: 0.85rem; color: var(--text-muted); }
        .course-label { font-size: 0.75rem; color: var(--text-muted); }
        .text-muted { color: var(--text-muted) !important; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/student_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-journal-text me-2"></i>Assignments</h2>
                <p class="text-muted mb-0">View and submit your assignments</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <div class="value" style="color: var(--accent)"><?= $pendingCount ?></div>
                    <div class="label">Pending</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <div class="value" style="color: #3b82f6"><?= $submittedCount ?></div>
                    <div class="label">Submitted</div>
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
                    <div class="value" style="color: var(--red)"><?= $overdueCount ?></div>
                    <div class="label">Overdue</div>
                </div>
            </div>
        </div>

        <!-- Assignments List -->
        <?php if (empty($assignments)): ?>
        <div class="xp-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-journal-text fs-1 text-muted d-block mb-3"></i>
                <h4>No Assignments Yet</h4>
                <p class="text-muted">Check back later for new assignments from your teachers</p>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($assignments as $a): 
            $daysLeft = $a['due_date'] ? floor((strtotime($a['due_date']) - time()) / 86400) : null;
            $dueText = $a['due_date'] ? date('M j, Y g:i A', strtotime($a['due_date'])) : 'No due date';
            
            if ($a['submission_status'] === 'graded') {
                $statusClass = 'graded';
                $statusText = 'Graded';
            } elseif ($a['submission_id']) {
                $statusClass = 'submitted';
                $statusText = 'Submitted';
            } elseif ($a['status'] === 'overdue') {
                $statusClass = 'overdue';
                $statusText = 'Overdue';
            } else {
                $statusClass = 'pending';
                $statusText = 'Pending';
            }
        ?>
        <div class="assignment-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="flex-grow-1">
                    <h5 class="mb-1"><?= e($a['title']) ?></h5>
                    <div class="course-label"><?= e($a['course_name'] ?? $a['course_code']) ?></div>
                </div>
                <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
            </div>
            
            <?php if ($a['description']): ?>
            <p class="text-muted small mb-2"><?= nl2br(e($a['description'])) ?></p>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="due-date">
                    <i class="bi bi-clock me-1"></i>Due: <?= $dueText ?>
                    <?php if ($daysLeft !== null && $daysLeft > 0 && !$a['submission_id']): ?>
                    <span class="ms-2 badge bg-<?= $daysLeft <= 1 ? 'danger' : ($daysLeft <= 3 ? 'warning' : 'secondary') ?>">
                        <?= $daysLeft == 0 ? 'Due today' : $daysLeft . ' days left' ?>
                    </span>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex gap-2">
                    <?php if ($a['submission_status'] === 'graded'): ?>
                    <span class="badge bg-success">Grade: <?= number_format($a['grade'], 1) ?>/<?= $a['max_points'] ?></span>
                    <?php elseif ($a['submission_id']): ?>
                    <span class="badge bg-info">Submitted <?= date('M j, g:i A', strtotime($a['submitted_at'])) ?></span>
                    <?php else: ?>
                    <a href="submission.php?assignment=<?= $a['id'] ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-upload me-1"></i> Submit
                    </a>
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