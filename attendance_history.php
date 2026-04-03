<?php
/**
 * XPLabs - Attendance History
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::require();

$db = Database::getInstance();
$role = $_SESSION['user_role'];
$userId = Auth::id();

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student', 'LRN', 'Lab', 'Station', 'Assigned At', 'Task']);
    
    $exportRecords = $db->fetchAll(
        "SELECT sa.*, u.lrn, u.first_name, u.last_name, 
                ls.station_code, lf.name as floor_name, l.name as lab_name
         FROM station_assignments sa
         JOIN users u ON sa.user_id = u.id
         JOIN lab_stations ls ON sa.station_id = ls.id
         JOIN lab_floors lf ON ls.floor_id = lf.id
         LEFT JOIN labs l ON lf.lab_id = l.id
         WHERE $whereClause
         ORDER BY sa.assigned_at DESC
         LIMIT 5000",
        $params
    );
    foreach ($exportRecords as $r) {
        fputcsv($out, [
            $r['first_name'] . ' ' . $r['last_name'],
            $r['lrn'],
            $r['lab_name'] ?? $r['floor_name'],
            $r['station_code'],
            date('Y-m-d H:i', strtotime($r['assigned_at'])),
            $r['task'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// Filters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$userFilter = $_GET['user_id'] ?? '';
$labFilter = $_GET['lab_id'] ?? '';

// Get labs for dropdown
$labs = $db->fetchAll("SELECT * FROM labs WHERE is_active = 1 ORDER BY name ASC");

// Build query
$where = ['1=1'];
$params = [];

if ($dateFrom) {
    $where[] = 'sa.assigned_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where[] = 'sa.assigned_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}
if ($userFilter) {
    $where[] = 'sa.user_id = ?';
    $params[] = $userFilter;
}
if ($labFilter) {
    $where[] = 'ls.floor_id IN (SELECT id FROM lab_floors WHERE lab_id = ?)';
    $params[] = $labFilter;
}

// For students, only show their own attendance
if ($role === 'student') {
    $where[] = 'sa.user_id = ?';
    $params[] = $userId;
}

$whereClause = implode(' AND ', $where);

$records = $db->fetchAll(
    "SELECT sa.*, u.lrn, u.first_name, u.last_name, 
            ls.station_code, lf.name as floor_name, l.name as lab_name
     FROM station_assignments sa
     JOIN users u ON sa.user_id = u.id
     JOIN lab_stations ls ON sa.station_id = ls.id
     JOIN lab_floors lf ON ls.floor_id = lf.id
     LEFT JOIN labs l ON lf.lab_id = l.id
     WHERE $whereClause
     ORDER BY sa.assigned_at DESC
     LIMIT 200",
    $params
);

// Get summary stats
$stats = $db->fetch(
    "SELECT 
            COUNT(*) as total_sessions,
            COUNT(DISTINCT sa.user_id) as unique_users
     FROM station_assignments sa
     WHERE $whereClause",
    $params
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance History - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0f172a; --bg-card: #1e293b; --border: #334155;
            --text: #e2e8f0; --text-muted: #94a3b8; --accent: #6366f1;
            --green: #22c55e; --yellow: #eab308; --red: #ef4444;
        }
        body { background: var(--bg-dark); color: var(--text); font-family: 'Segoe UI', system-ui, sans-serif; min-height: 100vh; }
        .sidebar { position: fixed; top: 0; left: 0; width: 260px; height: 100vh; background: var(--bg-card); border-right: 1px solid var(--border); z-index: 1000; overflow-y: auto; }
        .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .sidebar-brand h4 { margin: 0; font-weight: 700; color: #fff; }
        .sidebar-brand small { color: var(--text-muted); }
        .sidebar-nav { padding: 1rem 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem; color: var(--text-muted); text-decoration: none; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(99, 102, 241, 0.1); color: var(--accent); }
        .sidebar-nav a i { width: 20px; text-align: center; }
        .sidebar-nav .nav-section { padding: 0.5rem 1.5rem; font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted); margin-top: 0.5rem; }
        .main-content { margin-left: 260px; padding: 2rem; }
        .xp-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .xp-card .card-header { background: transparent; border-bottom: 1px solid var(--border); padding: 1rem 1.5rem; }
        .xp-card .card-header h5 { margin: 0; font-weight: 600; color: #fff; }
        .xp-card .card-body { padding: 1.5rem; }
        .xp-table { width: 100%; border-collapse: collapse; }
        .xp-table th, .xp-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        .xp-table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 600; }
        .xp-table tr:hover { background: rgba(99, 102, 241, 0.05); }
        .form-control, .form-select { background: var(--bg-dark); border: 1px solid var(--border); color: var(--text); }
        .form-control:focus, .form-select:focus { border-color: var(--accent); box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25); }
        .form-label { color: var(--text-muted); font-size: 0.85rem; }
        .stat-card { background: var(--bg-dark); border: 1px solid var(--border); border-radius: 8px; padding: 1rem; text-align: center; }
        .stat-card .value { font-size: 1.5rem; font-weight: 700; color: #fff; }
        .stat-card .label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 0.5rem; }
        .status-dot.active { background: var(--green); }
    </style>
</head>
<body>
    <nav class="sidebar">
        <div class="sidebar-brand"><h4><i class="bi bi-flask me-2"></i>XPLabs</h4><small><?= ucfirst($role) ?> Portal</small></div>
        <div class="sidebar-nav">
            <a href="dashboard_<?= $role === 'student' ? 'student' : ($role === 'admin' ? 'admin' : 'teacher') ?>.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            <?php if ($role !== 'student'): ?>
            <a href="monitoring.php"><i class="bi bi-display"></i> Lab Monitor</a>
            <a href="lab_seatplan.php"><i class="bi bi-layout-text-window-reverse"></i> Seat Plan</a>
            <div class="nav-section">Management</div>
            <a href="admin_users.php"><i class="bi bi-people"></i> Users</a>
            <a href="admin_system.php"><i class="bi bi-gear"></i> Lab Settings</a>
            <div class="nav-section">Academic</div>
            <a href="assignments_manage.php"><i class="bi bi-journal-text"></i> Assignments</a>
            <a href="submissions.php"><i class="bi bi-upload"></i> Submissions</a>
            <?php endif; ?>
            <a href="announcements.php"><i class="bi bi-megaphone"></i> Announcements</a>
            <a href="attendance_history.php" class="active"><i class="bi bi-calendar-check"></i> Attendance</a>
            <a href="leaderboard.php"><i class="bi bi-trophy"></i> Leaderboard</a>
            <div class="nav-section mt-4">Account</div>
            <a href="api/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </nav>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-calendar-check me-2"></i>Attendance History</h2>
                <p class="text-muted mb-0">Track lab session attendance</p>
            </div>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
            </a>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="stat-card"><div class="value"><?= $stats['total_sessions'] ?? 0 ?></div><div class="label">Total Sessions</div></div></div>
            <div class="col-md-4"><div class="stat-card"><div class="value"><?= $stats['unique_users'] ?? 0 ?></div><div class="label">Unique Users</div></div></div>
            <div class="col-md-4"><div class="stat-card"><div class="value">—</div><div class="label">Avg. Duration</div></div></div>
        </div>

        <!-- Filters -->
        <div class="xp-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-2"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>"></div>
                    <div class="col-md-2"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>"></div>
                    <?php if ($role !== 'student'): ?>
                    <div class="col-md-2"><label class="form-label">Lab</label><select name="lab_id" class="form-select"><option value="">All Labs</option><?php foreach ($labs as $l): ?><option value="<?= $l['id'] ?>" <?= $labFilter == $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option><?php endforeach; ?></select></div>
                    <?php endif; ?>
                    <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Filter</button></div>
                    <div class="col-md-2 d-flex align-items-end"><a href="attendance_history.php" class="btn btn-outline-secondary w-100">Clear</a></div>
                </form>
            </div>
        </div>

        <!-- Records Table -->
        <div class="xp-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Session Records</h5>
                <span class="text-muted small"><?= count($records) ?> records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="xp-table">
                        <thead><tr><th>Student</th><th>Lab / Station</th><th>Assigned At</th><th>Task</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($records as $r): ?>
                            <tr>
                                <td><div class="fw-semibold"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></div><div class="text-muted small"><?= e($r['lrn']) ?></div></td>
                                <td><?= e($r['lab_name'] ?? $r['floor_name']) ?><div class="text-muted small"><?= e($r['station_code']) ?></div></td>
                                <td><?= date('M j, Y H:i', strtotime($r['assigned_at'])) ?></td>
                                <td class="small"><?= e($r['task'] ?? '—') ?></td>
                                <td><span class="status-dot active"></span> Active</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($records)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No attendance records found for this period</td></tr>
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