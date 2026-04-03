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
    <link href="<?= asset('css/lab-floor.css') ?>" rel="stylesheet">
    <style>
        :root { --xp-dark: #1e293b; --xp-radius: 0.75rem; }
        body { background: #f1f5f9; }
        .sidebar { position: fixed; top: 0; left: 0; width: 250px; height: 100vh; background: var(--xp-dark); color: #fff; padding: 1.5rem 0; z-index: 1000; }
        .sidebar-brand { padding: 0 1.5rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 1rem; }
        .sidebar-brand h4 { margin: 0; font-weight: 700; }
        .sidebar a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem; color: rgba(255,255,255,0.7); text-decoration: none; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.1); color: #fff; }
        .sidebar a .icon { width: 20px; text-align: center; }
        .main-content { margin-left: 250px; padding: 2rem; }
    </style>
</head>
<body>
    <nav class="sidebar">
        <div class="sidebar-brand"><h4>🧪 XPLabs</h4><small>Admin Panel</small></div>
        <a href="dashboard_admin.php"><span class="icon">📊</span> Dashboard</a>
        <a href="admin_users.php" class="active"><span class="icon">👥</span> Users</a>
        <a href="admin_system.php"><span class="icon">🖥️</span> Lab Management</a>
        <a href="admin_logs.php"><span class="icon">📋</span> Activity Logs</a>
        <a href="announcements.php"><span class="icon">📢</span> Announcements</a>
        <a href="leaderboard.php"><span class="icon">🏆</span> Leaderboard</a>
        <hr class="border-secondary mx-3">
        <a href="api/auth/logout.php"><span class="icon">🚪</span> Logout</a>
    </nav>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">👥 User Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">➕ Add User</button>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show">
            <?= e($message['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
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
                        <button type="submit" class="btn btn-primary w-100">🔍 Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="admin_users.php" class="btn btn-outline-secondary w-100">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>LRN</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users['data'] as $user): ?>
                            <tr>
                                <td><code><?= e($user['lrn']) ?></code></td>
                                <td><?= e($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                <td><?= e($user['email'] ?? '—') ?></td>
                                <td><span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'teacher' ? 'info' : 'primary') ?>"><?= ucfirst($user['role']) ?></span></td>
                                <td><span class="badge bg-<?= $user['is_active'] ? 'success' : 'secondary' ?>"><?= $user['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                <td><?= $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : 'Never' ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">✏️ Edit</button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($users['data'])): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No users found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($users['last_page'] > 1): ?>
            <div class="card-footer bg-white">
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
                    <h5 class="modal-title">➕ Add New User</h5>
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
                    <h5 class="modal-title">✏️ Edit User</h5>
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