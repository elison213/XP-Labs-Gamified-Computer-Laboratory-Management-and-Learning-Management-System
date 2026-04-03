<?php
/**
 * XPLabs - Assignment Management (Teacher/Admin)
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::requireRole(['admin', 'teacher']);

$db = Database::getInstance();
$role = $_SESSION['user_role'];
$userId = Auth::id();

// Handle POST actions
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
                $db->insert('assignments', [
                    'title' => $_POST['title'],
                    'description' => $_POST['description'] ?? '',
                    'course_id' => (int) ($_POST['course_id'] ?? 0),
                    'created_by' => $userId,
                    'due_date' => $_POST['due_date'] ?: null,
                    'max_points' => (int) ($_POST['max_points'] ?? 100),
                    'allow_late' => !empty($_POST['allow_late']) ? 1 : 0,
                    'late_penalty_percent' => (int) ($_POST['late_penalty_percent'] ?? 10),
                    'is_active' => 1,
                ]);
                $message = ['type' => 'success', 'text' => 'Assignment created successfully'];
                break;
            case 'update':
                $db->update('assignments', [
                    'title' => $_POST['title'],
                    'description' => $_POST['description'] ?? '',
                    'course_id' => (int) ($_POST['course_id'] ?? 0),
                    'due_date' => $_POST['due_date'] ?: null,
                    'max_points' => (int) ($_POST['max_points'] ?? 100),
                    'allow_late' => !empty($_POST['allow_late']) ? 1 : 0,
                    'late_penalty_percent' => (int) ($_POST['late_penalty_percent'] ?? 10),
                ], 'id = ?', [(int) $_POST['assignment_id']]);
                $message = ['type' => 'success', 'text' => 'Assignment updated successfully'];
                break;
            case 'delete':
                $db->update('assignments', ['status' => 'archived'], 'id = ?', [(int) $_POST['assignment_id']]);
                $message = ['type' => 'success', 'text' => 'Assignment deleted'];
                break;
        }
    } catch (\Exception $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}

// Get courses for dropdown
$courses = $db->fetchAll("SELECT * FROM courses WHERE status != 'archived' ORDER BY name ASC");

// Get assignments
$assignments = $db->fetchAll(
    "SELECT a.*, c.name as course_name,
            COUNT(DISTINCT s.id) as submission_count,
            COUNT(DISTINCT CASE WHEN s.status = 'submitted' THEN s.id END) as submitted_count
     FROM assignments a
     LEFT JOIN courses c ON a.course_id = c.id
     LEFT JOIN submissions s ON a.id = s.assignment_id
     WHERE a.status != 'archived'
     GROUP BY a.id
     ORDER BY a.due_date ASC, a.created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assignments - XPLabs</title>
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
        .form-control, .form-select { background: #fff; border-color: #e2e8f0; color: #1e293b; }
        .form-label { color: #64748b; }
        .text-muted { color: #64748b !important; }
        .text-white { color: #1e293b !important; }
        .modal-content { background: #fff; }
        .modal-header { border-color: #e2e8f0; }
        .modal-footer { border-color: #e2e8f0; }
        <?php endif; ?>

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
        .status-pill.open { background: rgba(34, 197, 94, 0.1); color: var(--green); }
        .status-pill.due-soon { background: rgba(234, 179, 8, 0.1); color: var(--yellow); }
        .status-pill.overdue { background: rgba(239, 68, 68, 0.1); color: var(--red); }
        .status-pill.closed { background: rgba(100, 116, 139, 0.1); color: var(--text-muted); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/admin_sidebar.php'; ?>
<div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-journal-text me-2"></i>Manage Assignments</h2>
                <p class="text-muted mb-0">Create and manage course assignments</p>
            </div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreate">
                <i class="bi bi-plus-lg me-1"></i> New Assignment
            </button>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show">
            <?= e($message['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="xp-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>All Assignments</h5>
                <span class="text-muted small"><?= count($assignments) ?> assignments</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="xp-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Course</th>
                                <th>Due Date</th>
                                <th>Points</th>
                                <th>Submissions</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $a): 
                                $now = time();
                                $due = $a['due_date'] ? strtotime($a['due_date']) : 0;
                                if ($due && $due < $now) {
                                    $status = 'overdue';
                                    $statusText = 'Overdue';
                                } elseif ($due && $due - $now < 86400 * 2) {
                                    $status = 'due-soon';
                                    $statusText = 'Due Soon';
                                } elseif ($due) {
                                    $status = 'open';
                                    $statusText = 'Open';
                                } else {
                                    $status = 'closed';
                                    $statusText = 'No Deadline';
                                }
                                $submitted = $a['submitted_count'] ?? 0;
                                $total = $a['submission_count'] ?? 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($a['title']) ?></div>
                                    <?php if ($a['description']): ?>
                                    <div class="text-muted small text-truncate" style="max-width: 300px"><?= e(substr($a['description'], 0, 60)) ?>...</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($a['course_name'] ?? '—') ?></td>
                                <td><?= $a['due_date'] ? date('M j, Y', strtotime($a['due_date'])) : '<span class="text-muted">—</span>' ?></td>
                                <td><?= $a['max_points'] ?> pts</td>
                                <td>
                                    <span class="text-success"><?= $submitted ?></span> / <?= $total ?>
                                </td>
                                <td><span class="status-pill <?= $status ?>"><?= $statusText ?></span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editAssignment(<?= htmlspecialchars(json_encode($a)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this assignment?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($assignments)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No assignments yet. Create your first one!
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Modal -->
    <div class="modal fade" id="modalCreate" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">New Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Course</label>
                            <select name="course_id" class="form-select">
                                <option value="">Select course...</option>
                                <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="datetime-local" name="due_date" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Points</label>
                            <input type="number" name="max_points" class="form-control" value="100" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Late Penalty %</label>
                            <input type="number" name="late_penalty_percent" class="form-control" value="10" min="0" max="100">
                        </div>
                        <div class="col-md-4 mb-3 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="allow_late" class="form-check-input" id="allowLate" checked>
                                <label class="form-check-label" for="allowLate">Allow late submissions</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Assignment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="modalEdit" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="assignment_id" id="edit-id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" id="edit-title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit-description" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Course</label>
                            <select name="course_id" id="edit-course" class="form-select">
                                <option value="">Select course...</option>
                                <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="datetime-local" name="due_date" id="edit-due" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Points</label>
                            <input type="number" name="max_points" id="edit-points" class="form-control" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Late Penalty %</label>
                            <input type="number" name="late_penalty_percent" id="edit-penalty" class="form-control" min="0" max="100">
                        </div>
                        <div class="col-md-4 mb-3 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="allow_late" class="form-check-input" id="edit-allow-late">
                                <label class="form-check-label" for="edit-allow-late">Allow late submissions</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editAssignment(a) {
        document.getElementById('edit-id').value = a.id;
        document.getElementById('edit-title').value = a.title;
        document.getElementById('edit-description').value = a.description || '';
        document.getElementById('edit-course').value = a.course_id || '';
        document.getElementById('edit-due').value = a.due_date ? a.due_date.replace(' ', 'T').substring(0, 16) : '';
        document.getElementById('edit-points').value = a.max_points;
        document.getElementById('edit-penalty').value = a.late_penalty_percent || 10;
        document.getElementById('edit-allow-late').checked = a.allow_late == 1;
        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }
    </script>
</body>
</html>