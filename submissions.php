<?php
/**
 * XPLabs - Submissions Management (Teacher/Admin)
 * View and grade student submissions
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::requireRole(['admin', 'teacher']);

$db = Database::getInstance();
$role = $_SESSION['user_role'];
$userId = Auth::id();

// Get all courses for filter
$courses = $db->fetchAll("SELECT * FROM courses ORDER BY name ASC");

// Filters
$courseId = (int) ($_GET['course_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';

// Build query for assignments
$where = ['1=1'];
$params = [];

if ($courseId) {
    $where[] = 'a.course_id = ?';
    $params[] = $courseId;
}
if ($statusFilter !== '') {
    $where[] = 's.status = ?';
    $params[] = $statusFilter;
}

if ($role === 'teacher') {
    $where[] = 'a.created_by = ?';
    $params[] = $userId;
}

$whereClause = implode(' AND ', $where);

// Get all submissions with student and assignment info
$submissions = $db->fetchAll(
    "SELECT s.*, a.title as assignment_title, a.max_points, a.due_date,
            c.name as course_name, c.code as course_code,
            u.lrn, u.first_name, u.last_name
     FROM submissions s
     JOIN assignments a ON s.assignment_id = a.id
     JOIN courses c ON a.course_id = c.id
     JOIN users u ON s.user_id = u.id
     WHERE $whereClause
     ORDER BY 
         CASE 
             WHEN s.status = 'pending' THEN 1
             WHEN s.status = 'submitted' THEN 2
             ELSE 3
         END,
         s.submitted_at DESC",
    $params
);

// Handle grading submission
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'grade') {
        $submissionId = (int) $_POST['submission_id'];
        $score = (float) ($_POST['score'] ?? 0);
        $feedback = trim($_POST['feedback'] ?? '');
        $maxPoints = (float) ($_POST['max_points'] ?? 100);
        
        try {
            $db->update('submissions', [
                'score' => $score,
                'feedback' => $feedback,
                'status' => 'graded'
            ], 'id = ?', [$submissionId]);
            
            $message = ['type' => 'success', 'text' => 'Submission graded successfully'];
            $submissions = $db->fetchAll(
                "SELECT s.*, a.title as assignment_title, a.max_points, a.due_date,
                        c.name as course_name, c.code as course_code,
                        u.lrn, u.first_name, u.last_name
                 FROM submissions s
                 JOIN assignments a ON s.assignment_id = a.id
                 JOIN courses c ON a.course_id = c.id
                 JOIN users u ON s.user_id = u.id
                 WHERE $whereClause
                 ORDER BY 
                     CASE 
                         WHEN s.status = 'pending' THEN 1
                         WHEN s.status = 'submitted' THEN 2
                         ELSE 3
                     END,
                     s.submitted_at DESC",
                $params
            );
        } catch (\Exception $e) {
            $message = ['type' => 'danger', 'text' => 'Error grading submission: ' . $e->getMessage()];
        }
    }
}

// Count by status
$pendingCount = count(array_filter($submissions, fn($s) => $s['status'] === 'pending'));
$submittedCount = count(array_filter($submissions, fn($s) => $s['status'] === 'submitted'));
$gradedCount = count(array_filter($submissions, fn($s) => $s['status'] === 'graded'));
$lateCount = count(array_filter($submissions, fn($s) => $s['status'] === 'late'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submissions - XPLabs</title>
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

        .sidebar {
            position: fixed; top: 0; left: 0; width: 260px; height: 100vh;
            background: #1e293b; border-right: 1px solid #334155;
            z-index: 1000; overflow-y: auto;
        }
        .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid #334155; }
        .sidebar-brand h4 { margin: 0; font-weight: 700; color: #fff; }
        .sidebar-brand small { color: #94a3b8; }
        .sidebar-nav { padding: 1rem 0; }
        .sidebar-nav a {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.75rem 1.5rem; color: #94a3b8;
            text-decoration: none; transition: all 0.2s;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: rgba(99, 102, 241, 0.1); color: #6366f1;
        }
        .sidebar-nav a i { width: 20px; text-align: center; }
        .sidebar-nav .nav-section {
            padding: 0.5rem 1.5rem; font-size: 0.7rem;
            text-transform: uppercase; letter-spacing: 0.05em;
            color: #94a3b8; margin-top: 0.5rem;
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

        .stat-box {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 8px; padding: 1rem; text-align: center;
        }
        .stat-box .value { font-size: 1.5rem; font-weight: 700; color: var(--text); }
        .stat-box .label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; }

        .status-pill {
            display: inline-block; padding: 0.25rem 0.75rem;
            border-radius: 20px; font-size: 0.7rem; font-weight: 600;
        }
        .status-pill.pending { background: rgba(99, 102, 241, 0.1); color: var(--accent); }
        .status-pill.submitted { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .status-pill.graded { background: rgba(34, 197, 94, 0.1); color: var(--green); }
        .status-pill.late { background: rgba(234, 179, 8, 0.1); color: var(--yellow); }

        .form-control, .form-select {
            background: var(--bg-main); border: 1px solid var(--border); color: var(--text);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent); box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }
        .form-label { color: var(--text-muted); font-size: 0.85rem; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-upload me-2"></i>Submissions</h2>
                <p class="text-muted mb-0">View and grade student submissions</p>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show">
            <?= e($message['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

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
                    <div class="value" style="color: var(--yellow)"><?= $lateCount ?></div>
                    <div class="label">Late</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="xp-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-4">
                        <select name="course_id" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $courseId == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="submitted" <?= $statusFilter === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                            <option value="graded" <?= $statusFilter === 'graded' ? 'selected' : '' ?>>Graded</option>
                            <option value="late" <?= $statusFilter === 'late' ? 'selected' : '' ?>>Late</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="submissions.php" class="btn btn-outline-secondary w-100">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Submissions Table -->
        <div class="xp-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>All Submissions</h5>
                <span class="text-muted small"><?= count($submissions) ?> submissions</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="xp-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Assignment</th>
                                <th>Course</th>
                                <th>Submitted</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissions as $s): 
                                $statusClass = $s['status'] === 'graded' ? 'graded' : 
                                             ($s['status'] === 'late' ? 'late' : 
                                             ($s['status'] === 'submitted' ? 'submitted' : 'pending'));
                                $scoreDisplay = $s['score'] !== null ? number_format($s['score'], 1) . '/' . $s['max_points'] : '—';
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                    <div class="text-muted small"><?= e($s['lrn']) ?></div>
                                </td>
                                <td><?= e($s['assignment_title']) ?></td>
                                <td><?= e($s['course_name'] ?? $s['course_code']) ?></td>
                                <td><?= $s['submitted_at'] ? date('M j, H:i', strtotime($s['submitted_at'])) : '—' ?></td>
                                <td><?= $scoreDisplay ?></td>
                                <td><span class="status-pill <?= $statusClass ?>"><?= ucfirst($s['status']) ?></span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#gradeModal<?= $s['id'] ?>">
                                        <i class="bi bi-pencil"></i> Grade
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Grade Modal -->
                            <div class="modal fade" id="gradeModal<?= $s['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="POST" class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Grade Submission</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="grade">
                                            <input type="hidden" name="submission_id" value="<?= $s['id'] ?>">
                                            <input type="hidden" name="max_points" value="<?= $s['max_points'] ?>">
                                            
                                            <div class="mb-3">
                                                <strong>Student:</strong> <?= e($s['first_name'] . ' ' . $s['last_name']) ?>
                                            </div>
                                            <div class="mb-3">
                                                <strong>Assignment:</strong> <?= e($s['assignment_title']) ?>
                                            </div>
                                            <?php if ($s['content']): ?>
                                            <div class="mb-3">
                                                <strong>Content:</strong>
                                                <div class="p-2 bg-light rounded mt-2"><?= nl2br(e($s['content'])) ?></div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Score (0-<?= $s['max_points'] ?>)</label>
                                                <input type="number" name="score" class="form-control" min="0" max="<?= $s['max_points'] ?>" step="0.1" value="<?= $s['score'] ?? '' ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Feedback</label>
                                                <textarea name="feedback" class="form-control" rows="3"><?= e($s['feedback'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Save Grade</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($submissions)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No submissions found</td>
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