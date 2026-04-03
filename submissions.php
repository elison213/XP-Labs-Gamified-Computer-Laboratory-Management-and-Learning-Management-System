<?php
/**
 * XPLabs - Submissions Management (Teacher/Admin)
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::requireRole(['admin', 'teacher']);

$db = Database::getInstance();

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="submissions_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student', 'LRN', 'Assignment', 'Submitted At', 'Status', 'Grade', 'Max Points', 'Percentage']);
    
    $exportSubs = $db->fetchAll(
        "SELECT s.*, a.title as assignment_title, a.max_points, u.lrn, u.first_name, u.last_name
         FROM submissions s
         JOIN assignments a ON s.assignment_id = a.id
         JOIN users u ON s.user_id = u.id
         WHERE $whereClause
         ORDER BY s.submitted_at DESC LIMIT 5000",
        $params
    );
    foreach ($exportSubs as $s) {
        $pct = $s['max_points'] > 0 ? round(($s['grade'] / $s['max_points']) * 100, 1) : 0;
        fputcsv($out, [
            $s['first_name'] . ' ' . $s['last_name'],
            $s['lrn'],
            $s['assignment_title'],
            $s['submitted_at'] ?? '',
            $s['status'] ?: ($s['is_late'] ? 'late' : 'submitted'),
            $s['grade'] ?? '',
            $s['max_points'],
            $pct . '%'
        ]);
    }
    fclose($out);
    exit;
}
$role = $_SESSION['user_role'];
$userId = Auth::id();

// Handle POST actions
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'grade':
                $submissionId = (int) $_POST['submission_id'];
                $grade = (float) ($_POST['grade'] ?? 0);
                $feedback = $_POST['feedback'] ?? '';
                
                $db->update('submissions', [
                    'status' => 'graded',
                    'grade' => $grade,
                    'feedback' => $feedback,
                    'graded_by' => $userId,
                    'graded_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$submissionId]);
                
                $message = ['type' => 'success', 'text' => 'Submission graded successfully'];
                break;
        }
    } catch (\Exception $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}

// Filters
$assignmentId = (int) ($_GET['assignment_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';

// Get assignments for dropdown
$assignments = $db->fetchAll(
    "SELECT a.*, c.name as course_name 
     FROM assignments a 
     LEFT JOIN courses c ON a.course_id = c.id 
     WHERE a.status != 'archived'
     ORDER BY a.created_at DESC"
);

// Get submissions
$where = ['1=1'];
$params = [];

if ($assignmentId) {
    $where[] = 's.assignment_id = ?';
    $params[] = $assignmentId;
}
if ($statusFilter) {
    $where[] = 's.status = ?';
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $where);

$submissions = $db->fetchAll(
    "SELECT s.*, a.title as assignment_title, a.max_points, a.due_date,
            u.lrn, u.first_name, u.last_name
     FROM submissions s
     JOIN assignments a ON s.assignment_id = a.id
     JOIN users u ON s.user_id = u.id
     WHERE $whereClause
     ORDER BY 
         CASE s.status 
             WHEN 'submitted' THEN 1 
             WHEN 'late' THEN 2 
             WHEN 'graded' THEN 3 
             ELSE 4 
         END,
         s.submitted_at DESC",
    $params
);
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
        .status-pill.submitted { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .status-pill.late { background: rgba(234, 179, 8, 0.1); color: var(--yellow); }
        .status-pill.graded { background: rgba(34, 197, 94, 0.1); color: var(--green); }
        .status-pill.missing { background: rgba(100, 116, 139, 0.1); color: var(--text-muted); }

        .grade-badge {
            display: inline-block; padding: 0.25rem 0.5rem;
            border-radius: 4px; font-size: 0.8rem; font-weight: 700;
        }
        .grade-badge.high { background: rgba(34, 197, 94, 0.2); color: var(--green); }
        .grade-badge.medium { background: rgba(234, 179, 8, 0.2); color: var(--yellow); }
        .grade-badge.low { background: rgba(239, 68, 68, 0.2); color: var(--red); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/admin_sidebar.php'; ?>
<div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-upload me-2"></i>Submissions</h2>
                <p class="text-muted mb-0">Review and grade student submissions</p>
            </div>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
            </a>
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
                    <div class="col-md-4">
                        <select name="assignment_id" class="form-select">
                            <option value="">All Assignments</option>
                            <?php foreach ($assignments as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $assignmentId == $a['id'] ? 'selected' : '' ?>>
                                <?= e($a['title']) ?><?= $a['course_name'] ? ' (' . $a['course_name'] . ')' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="submitted" <?= $statusFilter === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                            <option value="late" <?= $statusFilter === 'late' ? 'selected' : '' ?>>Late</option>
                            <option value="graded" <?= $statusFilter === 'graded' ? 'selected' : '' ?>>Graded</option>
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
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Student Submissions</h5>
                <span class="text-muted small"><?= count($submissions) ?> submissions</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="xp-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Assignment</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Grade</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissions as $s): 
                                $isLate = $s['is_late'] ?? 0;
                                $statusClass = $s['status'] === 'graded' ? 'graded' : ($isLate ? 'late' : 'submitted');
                                $statusText = ucfirst($s['status'] ?? ($isLate ? 'Late' : 'Submitted'));
                                
                                $gradePercent = $s['max_points'] > 0 ? ($s['grade'] / $s['max_points']) * 100 : 0;
                                $gradeClass = $gradePercent >= 80 ? 'high' : ($gradePercent >= 50 ? 'medium' : 'low');
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                    <div class="text-muted small"><?= e($s['lrn']) ?></div>
                                </td>
                                <td><?= e($s['assignment_title']) ?></td>
                                <td>
                                    <?= $s['submitted_at'] ? date('M j, Y H:i', strtotime($s['submitted_at'])) : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td><span class="status-pill <?= $statusClass ?>"><?= $statusText ?></span></td>
                                <td>
                                    <?php if ($s['status'] === 'graded'): ?>
                                    <span class="grade-badge <?= $gradeClass ?>">
                                        <?= number_format($s['grade'], 1) ?> / <?= $s['max_points'] ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($s['status'] !== 'graded'): ?>
                                    <button class="btn btn-sm btn-primary" onclick="gradeSubmission(<?= $s['id'] ?>, <?= $s['max_points'] ?>)">
                                        <i class="bi bi-pencil me-1"></i> Grade
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="viewFeedback(<?= htmlspecialchars(json_encode($s)) ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($submissions)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No submissions found
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Grade Modal -->
    <div class="modal fade" id="modalGrade" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="grade">
                <input type="hidden" name="submission_id" id="grade-submission-id">
                <div class="modal-header">
                    <h5 class="modal-title">Grade Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Grade (out of <span id="max-points-display">100</span>) *</label>
                        <input type="number" name="grade" id="grade-input" class="form-control" min="0" step="0.1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Feedback</label>
                        <textarea name="feedback" class="form-control" rows="3" placeholder="Provide feedback to the student..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Grade</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function gradeSubmission(id, maxPoints) {
        document.getElementById('grade-submission-id').value = id;
        document.getElementById('max-points-display').textContent = maxPoints;
        document.getElementById('grade-input').max = maxPoints;
        document.getElementById('grade-input').value = '';
        new bootstrap.Modal(document.getElementById('modalGrade')).show();
    }
    </script>
</body>
</html>