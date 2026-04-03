<?php
/**
 * XPLabs - Admin Activity Logs (Admin only)
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::requireRole(['admin']);

$db = Database::getInstance();

// Filters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$searchFilter = $_GET['search'] ?? '';
$userFilter = $_GET['user_id'] ?? '';

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Timestamp', 'User', 'LRN', 'Action', 'Target', 'Details', 'IP Address']);
    
    $where = ['1=1'];
    $params = [];
    if ($dateFrom) { $where[] = 'al.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
    if ($dateTo) { $where[] = 'al.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }
    if ($userFilter) { $where[] = 'al.user_id = ?'; $params[] = $userFilter; }
    
    $logs = $db->fetchAll(
        "SELECT al.*, u.lrn, u.first_name, u.last_name FROM admin_logs al LEFT JOIN users u ON al.user_id = u.id WHERE " . implode(' AND ', $where) . " ORDER BY al.created_at DESC",
        array_merge($params, [5000])
    );
    foreach ($logs as $l) {
        fputcsv($out, [
            $l['created_at'],
            $l['first_name'] . ' ' . $l['last_name'],
            $l['lrn'] ?? '',
            $l['action'] ?? '',
            $l['target'] ?? '',
            $l['details'] ?? '',
            $l['ip_address'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// Get users for dropdown
$users = $db->fetchAll("SELECT id, first_name, last_name, role FROM users ORDER BY last_name ASC LIMIT 200");

// Build query
$where = ['1=1'];
$params = [];
if ($dateFrom) { $where[] = 'al.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
if ($dateTo) { $where[] = 'al.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }
if ($searchFilter) { 
    $where[] = '(al.action LIKE ? OR al.target LIKE ? OR al.details LIKE ?)'; 
    $searchTerm = '%' . $searchFilter . '%';
    $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm;
}
if ($userFilter) { $where[] = 'al.user_id = ?'; $params[] = $userFilter; }

$whereClause = implode(' AND ', $where);

$logs = $db->fetchAll(
    "SELECT al.*, u.lrn, u.first_name, u.last_name, u.role as user_role 
     FROM admin_logs al 
     LEFT JOIN users u ON al.user_id = u.id 
     WHERE $whereClause
     ORDER BY al.created_at DESC
     LIMIT 500",
    $params
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0f172a; --bg-card: #1e293b; --border: #334155;
            --text: #e2e8f0; --text-muted: #94a3b8; --accent: #6366f1;
            --green: #22c55e; --yellow: #eab308; --red: #ef4444;
        }
        body { background: var(--bg-dark); color: var(--text); font-family: 'Segoe UI', system-ui, sans-serif; min-height: 100vh; }
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
        <?php endif; ?>
        .sidebar { position: fixed; top: 0; left: 0; width: 260px; height: 100vh; background: var(--bg-card); border-right: 1px solid var(--border); z-index: 1000; overflow-y: auto; }
        .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .sidebar-brand h4 { margin: 0; font-weight: 700; color: #fff; }
        .sidebar-nav { padding: 1rem 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem; color: var(--text-muted); text-decoration: none; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(99, 102, 241, 0.1); color: var(--accent); }
        .sidebar-nav a i { width: 20px; text-align: center; }
        .sidebar-nav .nav-section { padding: 0.5rem 1.5rem; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-top: 0.5rem; }
        .main-content { margin-left: 260px; padding: 2rem; }
        .xp-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .xp-card .card-header { background: transparent; border-bottom: 1px solid var(--border); padding: 1rem 1.5rem; }
        .xp-card .card-header h5 { margin: 0; font-weight: 600; color: #fff; }
        .xp-card .card-body { padding: 1.5rem; }
        .xp-table { width: 100%; border-collapse: collapse; }
        .xp-table th, .xp-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border); font-size: 0.8rem; }
        .xp-table th { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 600; }
        .xp-table tr:hover { background: rgba(99, 102, 241, 0.05); }
        .form-control, .form-select { background: var(--bg-dark); border: 1px solid var(--border); color: var(--text); }
        .form-control:focus, .form-select:focus { border-color: var(--accent); box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25); }
        .form-label { color: var(--text-muted); font-size: 0.85rem; }
        .action-badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 600; background: var(--bg-dark); border: 1px solid var(--border); color: var(--text-muted); }
        .action-badge.create { border-color: var(--green); color: var(--green); }
        .action-badge.update { border-color: #3b82f6; color: #3b82f6; }
        .action-badge.delete { border-color: var(--red); color: var(--red); }
        .action-badge.login { border-color: var(--accent); color: var(--accent); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/admin_sidebar.php'; ?>
<div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-activity me-2"></i>Activity Logs</h2>
                <p class="text-muted mb-0">System audit trail and user actions</p>
            </div>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
            </a>
        </div>

        <!-- Filters -->
        <div class="xp-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">User</label>
                        <select name="user_id" class="form-select form-select-sm">
                            <option value="">All Users</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $userFilter == $u['id'] ? 'selected' : '' ?>><?= e($u['first_name'] . ' ' . $u['last_name']) ?> (<?= $u['role'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="search" name="search" class="form-control form-control-sm" placeholder="Action, target, details..." value="<?= e($searchFilter) ?>">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <a href="admin_logs.php" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="xp-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Activity Log</h5>
                <span class="text-muted small"><?= count($logs) ?> entries</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="xp-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Target</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="text-nowrap"><code class="small"><?= date('m/d/Y H:i', strtotime($log['created_at'])) ?></code></td>
                                <td>
                                    <div class="fw-semibold small"><?= e($log['first_name'] . ' ' . $log['last_name'] ?? 'Unknown') ?></div>
                                    <div class="text-muted" style="font-size: 0.65rem;"><?= $log['user_role'] ?? 'N/A' ?></div>
                                </td>
                                <td><span class="action-badge <?= strtolower(explode('_', $log['action'])[0]) ?? '' ?>"><?= e($log['action'] ?? '-') ?></span></td>
                                <td class="small"><?= e($log['target'] ?? '-') ?></td>
                                <td class="small text-muted"><?= e($log['details'] ?? '') ?></td>
                                <td><code class="small"><?= e($log['ip_address'] ?? '-') ?></code></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($logs)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No activity logs found</td></tr>
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