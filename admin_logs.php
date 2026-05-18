<?php
/**
 * XPLabs - Admin Activity Logs (audit + lab desktop activity)
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Services\AdminLogService;
use XPLabs\Services\PcActivityService;

Auth::requireRole(['admin']);

$db = Database::getInstance();
$logService = new AdminLogService();
$pcActivity = new PcActivityService();

$view = ($_GET['view'] ?? 'audit') === 'desktop' ? 'desktop' : 'audit';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$searchFilter = trim((string) ($_GET['search'] ?? ''));
$userFilter = $_GET['user_id'] ?? '';
$pcFilter = (int) ($_GET['pc_id'] ?? 0);
$eventTypeFilter = trim((string) ($_GET['event_type'] ?? ''));

$filterParams = [
    'view' => $view,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'search' => $searchFilter,
    'user_id' => $userFilter,
    'pc_id' => $pcFilter > 0 ? $pcFilter : '',
    'event_type' => $eventTypeFilter,
];
$filterQuery = http_build_query(array_filter($filterParams, static fn ($v) => $v !== '' && $v !== null));

// Handle export (audit only)
if ($view === 'audit' && isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Timestamp', 'User', 'LRN', 'Action', 'Target', 'Details', 'IP Address']);

    $logs = $logService->list([
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'search' => $searchFilter,
        'user_id' => $userFilter,
    ], 5000);

    foreach ($logs as $l) {
        fputcsv($out, [
            $l['created_at'],
            trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')),
            $l['lrn'] ?? '',
            $l['action'] ?? '',
            $logService->formatTarget($l['entity_type'] ?? null, $l['entity_id'] ?? null),
            $logService->formatDetailsForDisplay($l['details'] ?? ''),
            $l['ip_address'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$users = $db->fetchAll("SELECT id, first_name, last_name, role FROM users ORDER BY last_name ASC LIMIT 300");
$pcs = $db->fetchAll("SELECT id, hostname FROM lab_pcs ORDER BY hostname ASC LIMIT 500");

$logs = [];
$desktopEvents = [];
$tableMissing = false;

if ($view === 'desktop') {
    if (!$pcActivity->tableExists()) {
        $tableMissing = true;
    } else {
        $desktopEvents = $pcActivity->listForDashboard([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'search' => $searchFilter,
            'pc_id' => $pcFilter,
            'event_type' => $eventTypeFilter,
        ], 300);
    }
} else {
    if (!$logService->tableExists()) {
        $tableMissing = true;
    } else {
        $logs = $logService->list([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'search' => $searchFilter,
            'user_id' => $userFilter,
        ], 500);
    }
}
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
            --bg-main: #f1f5f9; --bg-card: #ffffff; --border: #e2e8f0;
            --text: #1e293b; --text-muted: #64748b; --accent: #6366f1;
            --green: #22c55e; --yellow: #eab308; --red: #ef4444;
        }
        body { background: var(--bg-main); color: var(--text); font-family: 'Segoe UI', system-ui, sans-serif; min-height: 100vh; }
        .main-content { margin-left: 260px; padding: 2rem; }
        .xp-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .xp-card .card-header { background: transparent; border-bottom: 1px solid var(--border); padding: 1rem 1.5rem; }
        .xp-card .card-body { padding: 1.5rem; }
        .xp-table { width: 100%; border-collapse: collapse; }
        .xp-table th, .xp-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border); font-size: 0.8rem; }
        .xp-table th { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 600; }
        .xp-table tr:hover { background: rgba(99, 102, 241, 0.05); }
        .form-control, .form-select { background: var(--bg-main); border: 1px solid var(--border); color: var(--text); }
        .action-badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 600; background: var(--bg-main); border: 1px solid var(--border); color: var(--text-muted); }
        .action-badge.login { border-color: var(--accent); color: var(--accent); }
        .action-badge.delete { border-color: var(--red); color: var(--red); }
        .view-tabs .nav-link { color: var(--text-muted); }
        .view-tabs .nav-link.active { color: var(--accent); font-weight: 600; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/admin_sidebar.php'; ?>
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="mb-1"><i class="bi bi-activity me-2"></i>Activity Logs</h2>
                <p class="text-muted mb-0">System audit trail and lab desktop activity from widgets</p>
            </div>
            <?php if ($view === 'audit'): ?>
            <a href="?<?= e($filterQuery . ($filterQuery ? '&' : '') . 'export=csv') ?>" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
            </a>
            <?php endif; ?>
        </div>

        <ul class="nav nav-tabs view-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link <?= $view === 'audit' ? 'active' : '' ?>" href="?<?= e(http_build_query(array_merge($filterParams, ['view' => 'audit']))) ?>">System audit</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view === 'desktop' ? 'active' : '' ?>" href="?<?= e(http_build_query(array_merge($filterParams, ['view' => 'desktop']))) ?>">Lab desktop activity</a>
            </li>
        </ul>

        <div class="xp-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <input type="hidden" name="view" value="<?= e($view) ?>">
                    <div class="col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
                    </div>
                    <?php if ($view === 'audit'): ?>
                    <div class="col-md-2">
                        <label class="form-label">User</label>
                        <select name="user_id" class="form-select form-select-sm">
                            <option value="">All Users</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (string) $userFilter === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['first_name'] . ' ' . $u['last_name']) ?> (<?= $u['role'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="col-md-2">
                        <label class="form-label">PC</label>
                        <select name="pc_id" class="form-select form-select-sm">
                            <option value="">All PCs</option>
                            <?php foreach ($pcs as $pc): ?>
                            <option value="<?= (int) $pc['id'] ?>" <?= $pcFilter === (int) $pc['id'] ? 'selected' : '' ?>><?= e($pc['hostname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Event</label>
                        <select name="event_type" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="app_focus" <?= $eventTypeFilter === 'app_focus' ? 'selected' : '' ?>>App focus</option>
                            <option value="idle" <?= $eventTypeFilter === 'idle' ? 'selected' : '' ?>>Idle</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="search" name="search" class="form-control form-control-sm" placeholder="<?= $view === 'audit' ? 'Action, user name, LRN, details...' : 'PC hostname, student, app name...' ?>" value="<?= e($searchFilter) ?>">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <a href="admin_logs.php?view=<?= e($view) ?>" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($tableMissing): ?>
        <div class="alert alert-warning">
            <?php if ($view === 'desktop'): ?>
            Desktop activity table missing. Run migration <code>database/migrations/052_pc_activity_events.sql</code> on the server.
            <?php else: ?>
            Audit log table missing. Run migration <code>database/migrations/020_create_admin_logs.sql</code>.
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="xp-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?= $view === 'desktop' ? 'Desktop activity' : 'Audit log' ?></h5>
                <span class="text-muted small"><?= $view === 'desktop' ? count($desktopEvents) : count($logs) ?> entries</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if ($view === 'desktop'): ?>
                    <table class="xp-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>PC</th>
                                <th>Student</th>
                                <th>Event</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($desktopEvents as $ev): ?>
                            <tr>
                                <td class="text-nowrap"><code class="small"><?= date('m/d/Y H:i', strtotime($ev['created_at'])) ?></code></td>
                                <td><?= e($ev['hostname'] ?? ('PC #' . $ev['pc_id'])) ?></td>
                                <td>
                                    <?php if (!empty($ev['first_name'])): ?>
                                    <div class="fw-semibold small"><?= e($ev['first_name'] . ' ' . $ev['last_name']) ?></div>
                                    <div class="text-muted" style="font-size:0.65rem;"><?= e($ev['lrn'] ?? '') ?></div>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="action-badge"><?= e($ev['event_type']) ?></span></td>
                                <td class="small text-muted"><?= e(PcActivityService::summarizePayload($ev['event_payload'] ?? '')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($desktopEvents) && !$tableMissing): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No desktop activity yet. Students must be signed in with the widget running on lab PCs.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <table class="xp-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Target</th>
                                <th>Details</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="text-nowrap"><code class="small"><?= date('m/d/Y H:i', strtotime($log['created_at'])) ?></code></td>
                                <td>
                                    <div class="fw-semibold small"><?= e(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: 'System') ?></div>
                                    <div class="text-muted" style="font-size:0.65rem;"><?= e($log['user_role'] ?? '') ?></div>
                                </td>
                                <td><span class="action-badge <?= strpos($log['action'] ?? '', 'login') !== false ? 'login' : '' ?>"><?= e($log['action'] ?? '-') ?></span></td>
                                <td class="small"><?= e($logService->formatTarget($log['entity_type'] ?? null, $log['entity_id'] ?? null)) ?></td>
                                <td class="small text-muted"><?= e($logService->formatDetailsForDisplay($log['details'] ?? '')) ?></td>
                                <td><code class="small"><?= e($log['ip_address'] ?? '-') ?></code></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($logs) && !$tableMissing): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No audit entries yet. Actions such as login, grading, and PC lock are recorded automatically.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
