<?php
/**
 * XPLabs - Admin Users Management
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Lib\PasswordPolicy;
use XPLabs\Services\AdminLogService;
use XPLabs\Services\UserService;

Auth::requireRole('admin');

$userService = new UserService();
$db = Database::getInstance();
$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim((string) ($_GET['search'] ?? ''));
$roleFilter = (string) ($_GET['role'] ?? '');
$sectionFilter = trim((string) ($_GET['section'] ?? ''));
$gradeFilter = trim((string) ($_GET['grade_level'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');

$filters = [
    'search' => $search,
    'role' => $roleFilter,
    'section' => $sectionFilter,
    'grade_level' => $gradeFilter,
];
if ($statusFilter === 'active') {
    $filters['is_active'] = 1;
} elseif ($statusFilter === 'inactive') {
    $filters['is_active'] = 0;
}

$users = $userService->list($filters, $page);

// Handle form submission
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'create':
                $newRole = $_POST['role'] ?? 'student';
                $payload = [
                    'lrn' => $_POST['lrn'],
                    'first_name' => $_POST['first_name'],
                    'last_name' => $_POST['last_name'],
                    'email' => $_POST['email'] ?? null,
                    'role' => $newRole,
                ];
                if ($newRole === 'teacher' || $newRole === 'admin') {
                    $pw = (string) ($_POST['password'] ?? '');
                    $pw2 = (string) ($_POST['password_confirm'] ?? '');
                    if ($pw !== $pw2) {
                        throw new \RuntimeException('Password and confirmation do not match.');
                    }
                    $payload['password'] = $pw;
                }
                if ($newRole === 'student') {
                    $custom = trim((string) ($_POST['section_custom'] ?? ''));
                    $presetId = (int) ($_POST['section_preset_id'] ?? 0);
                    $section = '';
                    if ($custom !== '') {
                        $section = $custom;
                    } elseif ($presetId > 0 && $db->tableExists('section_presets')) {
                        $preset = $db->fetch('SELECT name FROM section_presets WHERE id = ? AND is_active = 1', [$presetId]);
                        $section = $preset['name'] ?? '';
                    }
                    if ($section !== '') {
                        $payload['section'] = $section;
                    }
                    $gl = trim((string) ($_POST['grade_level'] ?? ''));
                    if ($gl !== '') {
                        $payload['grade_level'] = $gl;
                    }
                }
                $newId = $userService->create($payload);
                (new AdminLogService())->log('create_user', 'user', (int) $newId, ['lrn' => $payload['lrn'], 'role' => $newRole]);
                $message = ['type' => 'success', 'text' => 'User created successfully'];
                break;
            case 'update':
                $upd = [
                    'first_name' => $_POST['first_name'],
                    'last_name' => $_POST['last_name'],
                    'email' => $_POST['email'],
                    'role' => $_POST['role'],
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ];
                if (($_POST['role'] ?? '') === 'student') {
                    $upd['section'] = $_POST['section'] ?? '';
                    $upd['grade_level'] = $_POST['grade_level'] ?? '';
                }
                $uid = (int) $_POST['user_id'];
                $userService->update($uid, $upd);
                (new AdminLogService())->log('update_user', 'user', $uid, ['role' => $upd['role'] ?? '']);
                $message = ['type' => 'success', 'text' => 'User updated successfully'];
                break;
            case 'delete':
                $uid = (int) $_POST['user_id'];
                $userService->delete($uid);
                (new AdminLogService())->log('delete_user', 'user', $uid);
                $message = ['type' => 'success', 'text' => 'User deleted successfully'];
                break;
            case 'reset_password':
                $uid = (int) ($_POST['user_id'] ?? 0);
                $pw = (string) ($_POST['password'] ?? '');
                $pw2 = (string) ($_POST['password_confirm'] ?? '');
                if ($pw !== $pw2) {
                    throw new \RuntimeException('Password and confirmation do not match.');
                }
                $target = $db->fetch('SELECT id, lrn, role, first_name, last_name FROM users WHERE id = ?', [$uid]);
                if (!$target) {
                    throw new \RuntimeException('User not found.');
                }
                $enforcePolicy = in_array($target['role'] ?? '', ['teacher', 'admin'], true)
                    || !empty($_POST['enforce_policy']);
                $userService->setPassword($uid, $pw, $enforcePolicy);
                $message = ['type' => 'success', 'text' => 'Password updated for ' . ($target['first_name'] ?? '') . ' ' . ($target['last_name'] ?? '')];
                break;
            case 'import':
                if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                    $role = $_POST['role'] ?? 'student';
                    $format = $_POST['import_format'] ?? 'legacy';
                    $tmp = $_FILES['csv_file']['tmp_name'];
                    $orig = (string) ($_FILES['csv_file']['name'] ?? '');
                    if ($format === 'masterlist') {
                        $results = $userService->importFromMasterlistUpload($tmp, $orig, $role);
                    } else {
                        $results = $userService->importFromFile($tmp, $role);
                    }
                    $message = ['type' => 'success', 'text' => "Import complete: {$results['success']} added, {$results['duplicate']} duplicates, {$results['error']} errors"];
                    if (!empty($results['errors'])) {
                        $message['text'] .= '. Errors: ' . implode(', ', array_slice($results['errors'], 0, 5));
                    }
                } else {
                    $message = ['type' => 'danger', 'text' => 'File upload error'];
                }
                break;
            case 'section_preset_create':
                if (!$db->tableExists('section_presets')) {
                    throw new \RuntimeException('Section presets are not available.');
                }
                $pname = trim((string) ($_POST['preset_name'] ?? ''));
                if ($pname === '') {
                    throw new \RuntimeException('Preset name is required.');
                }
                $sort = (int) ($_POST['preset_sort_order'] ?? 0);
                $db->insert('section_presets', [
                    'name' => $pname,
                    'sort_order' => $sort,
                    'is_active' => 1,
                ]);
                $message = ['type' => 'success', 'text' => 'Section preset added'];
                break;
            case 'section_preset_update':
                if (!$db->tableExists('section_presets')) {
                    throw new \RuntimeException('Section presets are not available.');
                }
                $pid = (int) ($_POST['preset_id'] ?? 0);
                $pname = trim((string) ($_POST['preset_name'] ?? ''));
                if ($pid <= 0 || $pname === '') {
                    throw new \RuntimeException('Invalid preset.');
                }
                $db->update('section_presets', [
                    'name' => $pname,
                    'sort_order' => (int) ($_POST['preset_sort_order'] ?? 0),
                    'is_active' => isset($_POST['preset_is_active']) ? 1 : 0,
                ], 'id = ?', [$pid]);
                $message = ['type' => 'success', 'text' => 'Section preset updated'];
                break;
            case 'section_preset_delete':
                if (!$db->tableExists('section_presets')) {
                    throw new \RuntimeException('Section presets are not available.');
                }
                $pid = (int) ($_POST['preset_id'] ?? 0);
                if ($pid <= 0) {
                    throw new \RuntimeException('Invalid preset.');
                }
                $db->delete('section_presets', 'id = ?', [$pid]);
                $message = ['type' => 'success', 'text' => 'Section preset removed'];
                break;
            case 'enroll_student_course':
                $studentId = (int) ($_POST['user_id'] ?? 0);
                $courseId = (int) ($_POST['course_id'] ?? 0);

                $student = $db->fetch("SELECT id FROM users WHERE id = ? AND role = 'student'", [$studentId]);
                $course = $db->fetch("SELECT id FROM courses WHERE id = ? AND status = 'active'", [$courseId]);
                if (!$student || !$course) {
                    throw new \RuntimeException('Invalid student or course selected.');
                }

                $existing = $db->fetch(
                    "SELECT id FROM course_enrollments WHERE course_id = ? AND user_id = ?",
                    [$courseId, $studentId]
                );
                if ($existing) {
                    $db->update('course_enrollments', [
                        'status' => 'enrolled',
                        'enrolled_by' => Auth::id(),
                        'enrolled_at' => date('Y-m-d H:i:s'),
                        'completed_at' => null,
                    ], 'id = ?', [$existing['id']]);
                } else {
                    $db->insert('course_enrollments', [
                        'course_id' => $courseId,
                        'user_id' => $studentId,
                        'enrolled_by' => Auth::id(),
                        'status' => 'enrolled',
                    ]);
                }
                $message = ['type' => 'success', 'text' => 'Student enrolled to course successfully'];
                break;
            case 'assign_teacher_course':
                $teacherId = (int) ($_POST['user_id'] ?? 0);
                $courseId = (int) ($_POST['course_id'] ?? 0);

                $teacher = $db->fetch("SELECT id FROM users WHERE id = ? AND role = 'teacher'", [$teacherId]);
                $course = $db->fetch("SELECT id FROM courses WHERE id = ? AND status = 'active'", [$courseId]);
                if (!$teacher || !$course) {
                    throw new \RuntimeException('Invalid teacher or course selected.');
                }

                $db->update('courses', ['teacher_id' => $teacherId], 'id = ?', [$courseId]);
                $message = ['type' => 'success', 'text' => 'Course assigned to teacher successfully'];
                break;
        }
    } catch (\Exception $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }

    // Refresh data
    $users = $userService->list($filters, $page);
    $sectionPresets = $db->tableExists('section_presets')
        ? $db->fetchAll('SELECT id, name, sort_order, is_active FROM section_presets ORDER BY sort_order ASC, name ASC')
        : [];
}

$activeCourses = $db->fetchAll(
    "SELECT id, code, name FROM courses WHERE status = 'active' ORDER BY name"
);

$sectionPresets = $db->tableExists('section_presets')
    ? $db->fetchAll('SELECT id, name, sort_order, is_active FROM section_presets ORDER BY sort_order ASC, name ASC')
    : [];
$sectionOptions = $userService->getDistinctSections();
$gradeOptions = $userService->getDistinctGradeLevels();
$passwordRequirements = PasswordPolicy::requirements();
$hasActiveFilters = $search !== '' || $roleFilter !== '' || $sectionFilter !== '' || $gradeFilter !== '' || $statusFilter !== '';

function admin_users_query_string(array $overrides = []): string
{
    $params = array_merge([
        'search' => $_GET['search'] ?? '',
        'role' => $_GET['role'] ?? '',
        'section' => $_GET['section'] ?? '',
        'grade_level' => $_GET['grade_level'] ?? '',
        'status' => $_GET['status'] ?? '',
        'page' => $_GET['page'] ?? 1,
    ], $overrides);
    $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);
    return $params ? ('?' . http_build_query($params)) : '';
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

        .form-control, .form-select {
            background: var(--bg-card); border: 1px solid var(--border); color: var(--text);
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
            background: var(--bg-card); border-color: var(--border); color: var(--text);
        }
        .pagination .page-item.active .page-link {
            background: var(--accent); border-color: var(--accent);
        }

        .filter-panel .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.25rem;
        }
        .filter-hint {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .active-filter-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .active-filter-chips .badge {
            font-weight: 500;
            padding: 0.4rem 0.65rem;
        }
        .password-rules {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin: 0;
            padding-left: 1.1rem;
        }
        .password-rules li { margin-bottom: 0.2rem; }
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
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalImport">
                    <i class="bi bi-upload me-1"></i> Import
                </button>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show">
            <?= e($message['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($db->tableExists('section_presets')): ?>
        <div class="xp-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-collection me-2"></i>Section presets</h5>
                <small class="text-muted">Used when creating students</small>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-2 align-items-end mb-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="section_preset_create">
                    <div class="col-md-4">
                        <label class="form-label">New preset name</label>
                        <input type="text" name="preset_name" class="form-control" placeholder="e.g., Newton" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Sort order</label>
                        <input type="number" name="preset_sort_order" class="form-control" value="0">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Add preset</button>
                    </div>
                </form>
                <?php if (empty($sectionPresets)): ?>
                    <p class="text-muted mb-0 small">No presets yet. Add one above or import students with sections.</p>
                <?php else: ?>
                    <?php foreach ($sectionPresets as $preset): ?>
                    <div class="border rounded p-2 mb-2 d-flex flex-wrap align-items-center gap-2">
                        <form method="POST" class="row g-2 align-items-center flex-grow-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="section_preset_update">
                            <input type="hidden" name="preset_id" value="<?= (int) $preset['id'] ?>">
                            <div class="col-md-4">
                                <input type="text" name="preset_name" class="form-control form-control-sm" value="<?= e($preset['name']) ?>" required>
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="preset_sort_order" class="form-control form-control-sm" value="<?= (int) $preset['sort_order'] ?>">
                            </div>
                            <div class="col-md-2">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="preset_is_active" value="1" id="pa<?= (int) $preset['id'] ?>" <?= (int) $preset['is_active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="pa<?= (int) $preset['id'] ?>">Active</label>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                            </div>
                        </form>
                        <form method="POST" class="mb-0" onsubmit="return confirm('Delete this preset?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="section_preset_delete">
                            <input type="hidden" name="preset_id" value="<?= (int) $preset['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="xp-card mb-4 filter-panel">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Find users</h5>
            </div>
            <div class="card-body">
                <p class="filter-hint mb-3">
                    Choose filters below, then click <strong>Apply filters</strong>.
                    For one class, set <strong>Role</strong> to Student and pick a <strong>Section</strong>.
                </p>
                <form method="GET" class="row g-3 align-items-end" id="user-filter-form">
                    <div class="col-md-4">
                        <label class="form-label" for="filter-search">Search</label>
                        <input type="text" id="filter-search" name="search" class="form-control" placeholder="Name, LRN, or email" value="<?= e($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="filter-role">Role</label>
                        <select id="filter-role" name="role" class="form-select">
                            <option value="">All roles</option>
                            <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Student</option>
                            <option value="teacher" <?= $roleFilter === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="filter-section">Section</label>
                        <select id="filter-section" name="section" class="form-select">
                            <option value="">All sections</option>
                            <?php foreach ($sectionOptions as $sec): ?>
                            <option value="<?= e($sec) ?>" <?= $sectionFilter === $sec ? 'selected' : '' ?>><?= e($sec) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="filter-grade">Grade</label>
                        <select id="filter-grade" name="grade_level" class="form-select">
                            <option value="">All grades</option>
                            <?php foreach ($gradeOptions as $gl): ?>
                            <option value="<?= e($gl) ?>" <?= $gradeFilter === $gl ? 'selected' : '' ?>><?= e($gl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="filter-status">Status</label>
                        <select id="filter-status" name="status" class="form-select">
                            <option value="">Any status</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active only</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive only</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2 pt-1">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Apply filters</button>
                        <?php if ($hasActiveFilters): ?>
                        <a href="admin_users.php" class="btn btn-outline-secondary">Clear all</a>
                        <?php endif; ?>
                    </div>
                </form>
                <?php if ($hasActiveFilters): ?>
                <div class="active-filter-chips mt-3 pt-3 border-top">
                    <span class="text-muted small me-1">Active:</span>
                    <?php if ($search !== ''): ?><span class="badge text-bg-primary">Search: <?= e($search) ?></span><?php endif; ?>
                    <?php if ($roleFilter !== ''): ?><span class="badge text-bg-secondary">Role: <?= e(ucfirst($roleFilter)) ?></span><?php endif; ?>
                    <?php if ($sectionFilter !== ''): ?><span class="badge text-bg-info">Section: <?= e($sectionFilter) ?></span><?php endif; ?>
                    <?php if ($gradeFilter !== ''): ?><span class="badge text-bg-info">Grade: <?= e($gradeFilter) ?></span><?php endif; ?>
                    <?php if ($statusFilter !== ''): ?><span class="badge text-bg-warning text-dark">Status: <?= e(ucfirst($statusFilter)) ?></span><?php endif; ?>
                </div>
                <?php endif; ?>
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
                                <th>Section</th>
                                <th>Status</th>
                                <th>Course</th>
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
                                <td class="small"><?= $user['role'] === 'student' ? e($user['section'] ?? '—') : '—' ?></td>
                                <td><span class="status-badge <?= $user['is_active'] ? 'active' : 'inactive' ?>"><?= $user['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                <td>
                                    <?php if ($user['role'] === 'student'): ?>
                                    <button class="btn btn-sm btn-outline-success"
                                            onclick="openCourseModal(<?= (int) $user['id'] ?>, 'student', '<?= e($user['first_name'] . ' ' . $user['last_name']) ?>')">
                                        Enroll
                                    </button>
                                    <?php elseif ($user['role'] === 'teacher'): ?>
                                    <button class="btn btn-sm btn-outline-info"
                                            onclick="openCourseModal(<?= (int) $user['id'] ?>, 'teacher', '<?= e($user['first_name'] . ' ' . $user['last_name']) ?>')">
                                        Assign
                                    </button>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : '<span class="text-muted">Never</span>' ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-warning" title="Reset password" onclick="openResetPasswordModal(<?= (int) $user['id'] ?>, <?= htmlspecialchars(json_encode($user['lrn']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($user['role']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))), ENT_QUOTES) ?>)"><i class="bi bi-key"></i></button>
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
                                <td colspan="9" class="text-center text-muted py-4">No users found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (($users['last_page'] ?? 1) > 1): ?>
            <div class="card-footer" style="background: var(--bg-card); border-top: 1px solid var(--border);">
                <nav>
                    <ul class="pagination mb-0 justify-content-center">
                        <?php for ($p = 1; $p <= $users['last_page']; $p++): ?>
                        <li class="page-item <?= $p === $users['page'] ? 'active' : '' ?>">
                            <a class="page-link" href="<?= e(admin_users_query_string(['page' => $p])) ?>"><?= $p ?></a>
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
                        <select name="role" id="create-user-role" class="form-select" required>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div id="create-staff-password-fields" class="d-none border rounded p-2 mb-3 bg-light">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" id="create-password" class="form-control mb-2" autocomplete="new-password" minlength="8">
                        <label class="form-label">Confirm password *</label>
                        <input type="password" name="password_confirm" id="create-password-confirm" class="form-control mb-2" autocomplete="new-password" minlength="8">
                        <ul class="password-rules mb-0">
                            <?php foreach ($passwordRequirements as $req): ?>
                            <li><?= e($req) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php if ($db->tableExists('section_presets')): ?>
                    <div id="create-student-section-fields" class="d-none border rounded p-2 mb-3 bg-light">
                        <label class="form-label">Section preset</label>
                        <select name="section_preset_id" class="form-select mb-2">
                            <option value="0">— Choose preset —</option>
                            <?php foreach ($sectionPresets as $sp): ?>
                                <?php if ((int) $sp['is_active'] !== 1) continue; ?>
                            <option value="<?= (int) $sp['id'] ?>"><?= e($sp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label">Custom section (optional, overrides preset)</label>
                        <input type="text" name="section_custom" class="form-control mb-2" placeholder="Type a section if not in the list">
                        <label class="form-label">Grade level (optional)</label>
                        <input type="text" name="grade_level" class="form-control" placeholder="e.g., 7">
                    </div>
                    <?php endif; ?>
                    <small id="create-password-hint-student" class="text-muted">New students sign in with their LRN as the initial password. Use <strong>Reset password</strong> in the user list to set a stronger password later.</small>
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
                    <div id="edit-student-extra-fields" class="mb-3 d-none">
                        <label class="form-label">Section</label>
                        <input type="text" name="section" id="edit-section" class="form-control mb-2">
                        <label class="form-label">Grade level</label>
                        <input type="text" name="grade_level" id="edit-grade-level" class="form-control">
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

    <!-- Import Modal -->
    <div class="modal fade" id="modalImport" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" enctype="multipart/form-data" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="import">
                <div class="modal-header">
                    <h5 class="modal-title">Import users</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Import mode</label>
                        <select name="import_format" id="import-format" class="form-select">
                            <option value="legacy">Simple CSV (first row header skipped; then LRN, first name, last name, email)</option>
                            <option value="masterlist">DepEd-style masterlist (finds “Students” block; requires LRN + Section columns)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">File *</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv,.xlsx,.xls" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Default Role</label>
                        <select name="role" class="form-select">
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                        </select>
                    </div>
                    <small class="text-muted d-block" id="import-help-legacy">Legacy CSV: the first row is ignored as a header; each following row is LRN, first name, last name, optional email.</small>
                    <small class="text-muted d-none" id="import-help-masterlist">Masterlist: .csv or .xlsx with a “Students” title cell; next row must name columns including LRN, Section, first name, and last name. Excel requires <code>composer install</code> for .xlsx.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset password -->
    <div class="modal fade" id="modalResetPassword" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset-user-id">
                <div class="modal-header">
                    <h5 class="modal-title">Reset password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">User: <strong id="reset-user-label"></strong></p>
                    <p class="small text-muted mb-3" id="reset-user-role-hint"></p>
                    <label class="form-label">New password</label>
                    <input type="password" name="password" id="reset-password" class="form-control mb-2" autocomplete="new-password" minlength="8" required>
                    <label class="form-label">Confirm new password</label>
                    <input type="password" name="password_confirm" id="reset-password-confirm" class="form-control mb-2" autocomplete="new-password" minlength="8" required>
                    <ul class="password-rules mb-3" id="reset-password-rules">
                        <?php foreach ($passwordRequirements as $req): ?>
                        <li><?= e($req) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="form-check d-none" id="reset-student-policy-wrap">
                        <input class="form-check-input" type="checkbox" name="enforce_policy" id="reset-enforce-policy" value="1">
                        <label class="form-check-label" for="reset-enforce-policy">Apply full password rules (recommended for staff accounts)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign/Enroll Course Modal -->
    <div class="modal fade" id="modalCourseAction" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="course-action-type">
                <input type="hidden" name="user_id" id="course-action-user-id">
                <div class="modal-header">
                    <h5 class="modal-title" id="course-action-title">Course Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">User: <strong id="course-action-user-name"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">Select course...</option>
                            <?php foreach ($activeCourses as $course): ?>
                            <option value="<?= (int) $course['id'] ?>"><?= e($course['code'] . ' - ' . $course['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <small class="text-muted" id="course-action-help"></small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="course-action-submit">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function syncCreateUserRoleFields() {
        const role = document.getElementById('create-user-role');
        const teacherBox = document.getElementById('create-staff-password-fields');
        const studentBox = document.getElementById('create-student-section-fields');
        const pw = document.getElementById('create-password');
        const pwc = document.getElementById('create-password-confirm');
        const hintSt = document.getElementById('create-password-hint-student');
        if (!role) return;
        const r = role.value;
        const staff = r === 'teacher' || r === 'admin';
        if (teacherBox) {
            teacherBox.classList.toggle('d-none', !staff);
            if (pw) { pw.required = staff; }
            if (pwc) { pwc.required = staff; }
        }
        if (studentBox) studentBox.classList.toggle('d-none', r !== 'student');
        if (hintSt) hintSt.classList.toggle('d-none', r !== 'student');
    }

    function syncFilterRoleFields() {
        const role = document.getElementById('filter-role')?.value || '';
        const studentOnly = role === '' || role === 'student';
        ['filter-section', 'filter-grade'].forEach((id) => {
            const el = document.getElementById(id);
            if (!el) return;
            el.disabled = !studentOnly;
            if (!studentOnly) el.value = '';
        });
    }
    document.getElementById('filter-role')?.addEventListener('change', syncFilterRoleFields);
    syncFilterRoleFields();

    function openResetPasswordModal(userId, lrn, role, name) {
        document.getElementById('reset-user-id').value = userId;
        document.getElementById('reset-user-label').textContent = name + ' (' + lrn + ')';
        const isStaff = role === 'teacher' || role === 'admin';
        const hint = document.getElementById('reset-user-role-hint');
        if (hint) {
            hint.textContent = isStaff
                ? 'Teachers and admins must use a strong password (see rules below).'
                : 'Students: minimum 8 characters; cannot equal their LRN.';
        }
        const rules = document.getElementById('reset-password-rules');
        if (rules) rules.classList.toggle('d-none', false);
        document.getElementById('reset-password').value = '';
        document.getElementById('reset-password-confirm').value = '';
        new bootstrap.Modal(document.getElementById('modalResetPassword')).show();
    }
    document.getElementById('create-user-role')?.addEventListener('change', syncCreateUserRoleFields);
    syncCreateUserRoleFields();

    document.getElementById('import-format')?.addEventListener('change', function () {
        const m = this.value === 'masterlist';
        document.getElementById('import-help-legacy')?.classList.toggle('d-none', m);
        document.getElementById('import-help-masterlist')?.classList.toggle('d-none', !m);
    });

    function editUser(user) {
        document.getElementById('edit-user-id').value = user.id;
        document.getElementById('edit-lrn').value = user.lrn;
        document.getElementById('edit-first-name').value = user.first_name;
        document.getElementById('edit-last-name').value = user.last_name;
        document.getElementById('edit-email').value = user.email || '';
        document.getElementById('edit-role').value = user.role;
        document.getElementById('edit-is-active').checked = user.is_active == 1;
        const sec = document.getElementById('edit-section');
        const gl = document.getElementById('edit-grade-level');
        const wrap = document.getElementById('edit-student-extra-fields');
        if (sec) sec.value = user.section || '';
        if (gl) gl.value = user.grade_level || '';
        if (wrap) {
            wrap.classList.toggle('d-none', user.role !== 'student');
            sec.disabled = user.role !== 'student';
            gl.disabled = user.role !== 'student';
        }
        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }

    document.getElementById('edit-role')?.addEventListener('change', function () {
        const wrap = document.getElementById('edit-student-extra-fields');
        const sec = document.getElementById('edit-section');
        const gl = document.getElementById('edit-grade-level');
        const st = this.value === 'student';
        if (wrap) wrap.classList.toggle('d-none', !st);
        if (sec) sec.disabled = !st;
        if (gl) gl.disabled = !st;
    });

    function openCourseModal(userId, userRole, userName) {
        document.getElementById('course-action-user-id').value = userId;
        document.getElementById('course-action-user-name').textContent = userName;

        const isStudent = userRole === 'student';
        document.getElementById('course-action-type').value = isStudent ? 'enroll_student_course' : 'assign_teacher_course';
        document.getElementById('course-action-title').textContent = isStudent ? 'Enroll Student to Course' : 'Assign Course to Teacher';
        document.getElementById('course-action-submit').textContent = isStudent ? 'Enroll Student' : 'Assign Course';
        document.getElementById('course-action-help').textContent = isStudent
            ? 'Only student-course enrollment is allowed in this action.'
            : 'Assign this course to the selected teacher to avoid wrong quiz/course ownership.';

        new bootstrap.Modal(document.getElementById('modalCourseAction')).show();
    }
    </script>
</body>
</html>