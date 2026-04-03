<?php
/**
 * XPLabs - Admin Users Management
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\UserService;

Auth::requireRole('admin');

$userService = new UserService();
$page = max(1, (int) ($_GET['page'] ?? 1));
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';

$filters = [
    'search' => $search,
    'role' => $roleFilter,
];

$users = $userService->list($filters, $page);

// Handle form submission
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'create':
                $userService->create([
                    'lrn' => $_POST['lrn'],
                    'first_name' => $_POST['first_name'],
                    'last_name' => $_POST['last_name'],
                    'email' => $_POST['email'] ?? null,
                    'role' => $_POST['role'],
                ]);
                $message = ['type' => 'success', 'text' => 'User created successfully'];
                break;
            case 'update':
                $userService->update((int) $_POST['user_id'], [
                    'first_name' => $_POST['first_name'],
                    'last_name' => $_POST['last_name'],
                    'email' => $_POST['email'],
                    'role' => $_POST['role'],
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ]);
                $message = ['type' => 'success', 'text' => 'User updated successfully'];
                break;
            case 'delete':
                $userService->delete((int) $_POST['user_id']);
                $message = ['type' => 'success', 'text' => 'User deleted successfully'];
                break;
            case 'import':
                if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                    $role = $_POST['role'] ?? 'student';
                    $results = $userService->importFromFile($_FILES['csv_file']['tmp_name'], $role);
                    $message = ['type' => 'success', 'text' => "Import complete: {$results['success']} added, {$results['duplicate']} duplicates, {$results['error']} errors"];
                    if (!empty($results['errors'])) {
                        $message['text'] .= '. Errors: ' . implode(', ', array_slice($results['errors'], 0, 5));
                    }
                } else {
                    $message = ['type' => 'danger', 'text' => 'File upload error'];
                }
                break;
        }
    } catch (\Exception $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }

    // Refresh data
    $users = $userService->list($filters, $page);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - XPLabs</title>
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
        .xp-table tr:hover { background: rgba(99,102,241,0.05); }
        .form-control, .form-select { background: #fff; border-color: #e2e8f0; color: #1e293b; }
        .form-label { color: #64748b; }
        .stat-card { background: #fff; border-color: #e2e8f0; }
        .stat-card .value { color: #1e293b; }
        .stat-card .label { color: #64748b; }
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

        .role-badge {
            padding: 0.25rem 0.5rem; border-radius: 4px;
            font-size: 0.7rem; font-weight: 600;
        }
        .role-badge.admin { background: rgba(239, 68, 68, 0.1); color: var(--red); }
        .role-badge.teacher { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .role-badge.student { background: rgba(99, 102, 241, 0.1); color: var(--accent); }

        .status-badge {
            padding: 0.25rem 0.5rem; border-radius: 4px;
            font-size: 0.7rem; font-weight: 600;
        }
        .status-badge.active { background: rgba(34, 197, 94, 0.1); color: var(--green); }
        .status-badge.inactive { background: rgba(100, 116, 139, 0.1); color: var(--text-muted); }

        .pagination .page-link {
            background: var(--bg-dark); border-color: var(--border); color: var(--text);
        }
        .pagination .page-item.active .page-link {
            background: var(--accent); border-color: var(--accent);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/admin_sidebar.php'; ?>
<div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-people me-2"></i>User Management</h2>
                <p class="text-muted mb-0">Manage students, teachers, and admin accounts</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreate">
                    <i class="bi bi-plus-lg me-1"></i> Add User
                </button>
                <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalImport">
                    <i class="bi bi-upload me-1"></i> Import CSV
                </button>
            </div>
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
                        <input type="text" name="search" class="form-control" placeholder="Search by name or LRN..." value="<?= e($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="role" class="form-select">
                            <option value="">All Roles</option>
                            <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Student</option>
                            <option value="teacher" <?= $roleFilter === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="admin_users.php" class="btn btn-outline-secondary w-100">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="xp-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>All Users</h5>
                <span class="text-muted small"><?= $users['total'] ?? 0 ?> users</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="xp-table">
                        <thead>
                            <tr>
                                <th>LRN</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users['data'] as $user): ?>
                            <tr>
                                <td><code><?= e($user['lrn']) ?></code></td>
                                <td>
                                    <div class="fw-semibold"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></div>
                                </td>
                                <td><?= e($user['email'] ?? '—') ?></td>
                                <td><span class="role-badge <?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span></td>
                                <td><span class="status-badge <?= $user['is_active'] ? 'active' : 'inactive' ?>"><?= $user['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                <td><?= $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : '<span class="text-muted">Never</span>' ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($users['data'])): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No users found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (($users['last_page'] ?? 1) > 1): ?>
            <div class="card-footer" style="background: var(--bg-dark); border-top: 1px solid var(--border);">
                <nav>
                    <ul class="pagination mb-0 justify-content-center">
                        <?php for ($p = 1; $p <= $users['last_page']; $p++): ?>
                        <li class="page-item <?= $p === $users['page'] ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="modalCreate" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">LRN *</label>
                        <input type="text" name="lrn" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role" class="form-select" required>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <small class="text-muted">Default password will be the user's LRN</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="modalEdit" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit-user-id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">LRN</label>
                        <input type="text" id="edit-lrn" class="form-control" readonly>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" id="edit-first-name" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" id="edit-last-name" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit-email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role" id="edit-role" class="form-select" required>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="edit-is-active" class="form-check-input" value="1" checked>
                        <label for="edit-is-active" class="form-check-label">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import CSV Modal -->
    <div class="modal fade" id="modalImport" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" enctype="multipart/form-data" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="import">
                <div class="modal-header">
                    <h5 class="modal-title">Import Users from CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">CSV File *</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Default Role</label>
                        <select name="role" class="form-select">
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                        </select>
                    </div>
                    <small class="text-muted">CSV format: lrn,first_name,last_name,email (email optional)</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editUser(user) {
        document.getElementById('edit-user-id').value = user.id;
        document.getElementById('edit-lrn').value = user.lrn;
        document.getElementById('edit-first-name').value = user.first_name;
        document.getElementById('edit-last-name').value = user.last_name;
        document.getElementById('edit-email').value = user.email || '';
        document.getElementById('edit-role').value = user.role;
        document.getElementById('edit-is-active').checked = user.is_active == 1;
        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }
    </script>
</body>
</html>