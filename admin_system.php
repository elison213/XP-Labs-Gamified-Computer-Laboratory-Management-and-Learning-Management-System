<?php
/**
 * XPLabs - Lab System Settings (Lab, Floor & Station Management)
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Services\LabService;

Auth::requireRole('admin');

$labService = new LabService();
$labs = $labService->getLabs();
$selectedLabId = (int) ($_GET['lab_id'] ?? ($labs[0]['id'] ?? 0));
$floors = $labService->getFloors($selectedLabId);
$selectedFloorId = (int) ($_GET['floor_id'] ?? ($floors[0]['id'] ?? 0));
$stations = $labService->getStations($selectedFloorId);
$stats = $labService->getStats($selectedFloorId);
$currentLab = null;
$currentFloor = null;
foreach ($labs as $l) { if ($l['id'] == $selectedLabId) { $currentLab = $l; break; } }
foreach ($floors as $f) { if ($f['id'] == $selectedFloorId) { $currentFloor = $f; break; } }

// Handle actions
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add_lab':
                $labService->createLab([
                    'name' => $_POST['name'],
                    'description' => $_POST['description'] ?? null,
                    'building' => $_POST['building'] ?? null,
                    'floor_number' => (int) ($_POST['floor_number'] ?? 1),
                    'grid_cols' => (int) ($_POST['grid_cols'] ?? 6),
                    'grid_rows' => (int) ($_POST['grid_rows'] ?? 5),
                ]);
                $message = ['type' => 'success', 'text' => 'Lab created successfully'];
                break;
            case 'update_lab':
                $labService->updateLab((int) $_POST['lab_id'], [
                    'name' => $_POST['name'],
                    'description' => $_POST['description'] ?? null,
                    'building' => $_POST['building'] ?? null,
                    'floor_number' => (int) ($_POST['floor_number'] ?? 1),
                    'grid_cols' => (int) ($_POST['grid_cols'] ?? 6),
                    'grid_rows' => (int) ($_POST['grid_rows'] ?? 5),
                ]);
                $message = ['type' => 'success', 'text' => 'Lab updated successfully'];
                break;
            case 'delete_lab':
                $labService->deleteLab((int) $_POST['lab_id']);
                $message = ['type' => 'success', 'text' => 'Lab deleted successfully'];
                break;
            case 'add_floor':
                $labService->createFloor([
                    'name' => $_POST['name'],
                    'lab_id' => $selectedLabId,
                    'building' => $_POST['building'] ?? null,
                    'floor_number' => (int) ($_POST['floor_number'] ?? 1),
                    'grid_cols' => (int) ($_POST['grid_cols'] ?? 6),
                    'grid_rows' => (int) ($_POST['grid_rows'] ?? 5),
                ]);
                $message = ['type' => 'success', 'text' => 'Floor created successfully'];
                break;
            case 'add_station':
                $labService->createStation([
                    'floor_id' => $selectedFloorId,
                    'station_code' => $_POST['station_code'],
                    'row_label' => $_POST['row_label'] ?? 'A',
                    'col_number' => (int) ($_POST['col_number'] ?? 1),
                    'status' => $_POST['status'] ?? 'offline',
                ]);
                $message = ['type' => 'success', 'text' => 'Station added successfully'];
                break;
            case 'update_station':
                $labService->updateStation((int) $_POST['station_id'], [
                    'station_code' => $_POST['station_code'],
                    'row_label' => $_POST['row_label'] ?? 'A',
                    'col_number' => (int) ($_POST['col_number'] ?? 1),
                    'status' => $_POST['status'] ?? 'offline',
                    'hostname' => $_POST['hostname'] ?? null,
                    'ip_address' => $_POST['ip_address'] ?? null,
                    'mac_address' => $_POST['mac_address'] ?? null,
                ]);
                $message = ['type' => 'success', 'text' => 'Station updated successfully'];
                break;
            case 'delete_station':
                $labService->deleteStation((int) $_POST['station_id']);
                $message = ['type' => 'success', 'text' => 'Station deleted successfully'];
                break;
            case 'bulk_status':
                $ids = array_filter(array_map('intval', explode(',', $_POST['station_ids'] ?? '')));
                if (!empty($ids)) {
                    $labService->bulkUpdateStatus($ids, $_POST['new_status'] ?? 'offline');
                    $message = ['type' => 'success', 'text' => 'Stations updated successfully'];
                }
                break;
        }
    } catch (\Exception $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }

    // Refresh data
    $labs = $labService->getLabs();
    $floors = $labService->getFloors($selectedLabId);
    $stations = $labService->getStations($selectedFloorId);
    $stats = $labService->getStats($selectedFloorId);
    $currentLab = null;
    $currentFloor = null;
    foreach ($labs as $l) { if ($l['id'] == $selectedLabId) { $currentLab = $l; break; } }
    foreach ($floors as $f) { if ($f['id'] == $selectedFloorId) { $currentFloor = $f; break; } }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Settings - XPLabs</title>
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
            --gray: #64748b;
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

        .stat-box {
            background: var(--bg-dark); border: 1px solid var(--border);
            border-radius: 8px; padding: 1rem; text-align: center;
        }
        .stat-box .value { font-size: 1.5rem; font-weight: 700; color: #fff; }
        .stat-box .label { font-size: 0.75rem; color: var(--text-muted); }

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

        .status-badge {
            padding: 0.25rem 0.5rem; border-radius: 4px;
            font-size: 0.75rem; font-weight: 600;
        }
        .status-badge.active { background: rgba(34, 197, 94, 0.1); color: var(--green); }
        .status-badge.idle { background: rgba(234, 179, 8, 0.1); color: var(--yellow); }
        .status-badge.offline { background: rgba(100, 116, 139, 0.1); color: var(--gray); }
        .status-badge.maintenance { background: rgba(239, 68, 68, 0.1); color: var(--red); }

        .lab-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .lab-tab {
            padding: 0.5rem 1.25rem; border-radius: 6px;
            background: var(--bg-card); border: 1px solid var(--border);
            color: var(--text-muted); text-decoration: none;
            transition: all 0.2s; display: flex; align-items: center; gap: 0.5rem;
        }
        .lab-tab:hover { border-color: var(--accent); color: var(--text); }
        .lab-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }
        .lab-tab .badge { font-size: 0.65rem; background: rgba(255,255,255,0.2); }

        .floor-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; }
        .floor-tab {
            padding: 0.5rem 1.25rem; border-radius: 6px;
            background: var(--bg-dark); border: 1px solid var(--border);
            color: var(--text-muted); text-decoration: none;
            transition: all 0.2s;
        }
        .floor-tab:hover { border-color: var(--accent); color: var(--text); }
        .floor-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }

        .form-control, .form-select {
            background: var(--bg-dark); border: 1px solid var(--border);
            color: var(--text);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent); box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }
        .form-label { color: var(--text-muted); font-size: 0.85rem; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-brand">
            <h4><i class="bi bi-flask me-2"></i>XPLabs</h4>
            <small>Admin Control Panel</small>
        </div>
        <div class="sidebar-nav">
            <a href="dashboard_admin.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            <a href="monitoring.php"><i class="bi bi-display"></i> Lab Monitor</a>
            <a href="lab_seatplan.php"><i class="bi bi-layout-text-window-reverse"></i> Seat Plan</a>
            
            <div class="nav-section">Management</div>
            <a href="admin_users.php"><i class="bi bi-people"></i> Users</a>
            <a href="admin_system.php" class="active"><i class="bi bi-gear"></i> Lab Settings</a>
            <a href="announcements.php"><i class="bi bi-megaphone"></i> Announcements</a>
            
            <div class="nav-section">Academic</div>
            <a href="assignments_manage.php"><i class="bi bi-journal-text"></i> Assignments</a>
            <a href="submissions.php"><i class="bi bi-upload"></i> Submissions</a>
            <a href="attendance_history.php"><i class="bi bi-calendar-check"></i> Attendance</a>
            
            <div class="nav-section">Gamification</div>
            <a href="leaderboard.php"><i class="bi bi-trophy"></i> Leaderboard</a>
            <a href="admin_logs.php"><i class="bi bi-activity"></i> Activity Logs</a>
            
            <div class="nav-section mt-4">Account</div>
            <a href="api/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </nav>

    <!-- Main -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-gear me-2"></i>Lab Settings</h2>
                <p class="text-muted mb-0">Manage labs, floors, and stations</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddLab">
                    <i class="bi bi-plus-lg me-1"></i> Add Lab
                </button>
                <?php if ($selectedLabId): ?>
                <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddFloor">
                    <i class="bi bi-building me-1"></i> Add Floor
                </button>
                <?php endif; ?>
                <?php if ($selectedFloorId): ?>
                <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddStation">
                    <i class="bi bi-pc-display me-1"></i> Add Station
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show">
            <?= e($message['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Lab Tabs -->
        <div class="lab-tabs">
            <?php foreach ($labs as $lab): 
                $floorCount = (int) $labService->getFloors($lab['id']) ? count($labService->getFloors($lab['id'])) : 0;
            ?>
            <a href="?lab_id=<?= $lab['id'] ?>" class="lab-tab <?= $lab['id'] == $selectedLabId ? 'active' : '' ?>">
                <i class="bi bi-building"></i> <?= e($lab['name']) ?>
                <span class="badge"><?= $floorCount ?> floors</span>
            </a>
            <?php endforeach; ?>
            <?php if (empty($labs)): ?>
            <span class="text-muted small">No labs configured yet</span>
            <?php endif; ?>
        </div>

        <?php if ($currentLab): ?>
        <!-- Floor Tabs -->
        <div class="floor-tabs">
            <?php foreach ($floors as $floor): ?>
            <a href="?lab_id=<?= $selectedLabId ?>&floor_id=<?= $floor['id'] ?>" class="floor-tab <?= $floor['id'] == $selectedFloorId ? 'active' : '' ?>">
                <?= e($floor['name']) ?>
            </a>
            <?php endforeach; ?>
            <?php if (empty($floors)): ?>
            <span class="text-muted small">No floors configured for this lab</span>
            <?php endif; ?>
        </div>

        <?php if ($selectedFloorId && $currentFloor): ?>
        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-2">
                <div class="stat-box">
                    <div class="value"><?= $stats['total'] ?></div>
                    <div class="label">Total</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-box">
                    <div class="value" style="color: var(--green)"><?= $stats['active'] ?></div>
                    <div class="label">Active</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-box">
                    <div class="value" style="color: var(--yellow)"><?= $stats['idle'] ?></div>
                    <div class="label">Idle</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-box">
                    <div class="value" style="color: var(--gray)"><?= $stats['offline'] ?></div>
                    <div class="label">Offline</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-box">
                    <div class="value" style="color: var(--red)"><?= $stats['maintenance'] ?></div>
                    <div class="label">Maintenance</div>
                </div>
            </div>
        </div>

        <!-- Station List -->
        <div class="xp-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-pc-display me-2"></i>Stations - <?= e($currentFloor['name']) ?></h5>
                <span class="text-muted small"><?= count($stations) ?> stations</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="xp-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all"></th>
                                <th>Code</th>
                                <th>Position</th>
                                <th>Status</th>
                                <th>User</th>
                                <th>Hostname</th>
                                <th>IP Address</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stations as $s): 
                                $status = $s['status'] ?? 'offline';
                                $user = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));
                            ?>
                            <tr>
                                <td><input type="checkbox" class="station-check" value="<?= $s['id'] ?>"></td>
                                <td><code><?= e($s['station_code']) ?></code></td>
                                <td><?= e($s['row_label'] ?? '-') ?>-<?= $s['col_number'] ?? '-' ?></td>
                                <td><span class="status-badge <?= $status ?>"><?= ucfirst($status) ?></span></td>
                                <td><?= $user ?: '<span class="text-muted">—</span>' ?></td>
                                <td><?= e($s['hostname'] ?? '—') ?></td>
                                <td><?= e($s['ip_address'] ?? '—') ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editStation(<?= htmlspecialchars(json_encode($s)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this station?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_station">
                                        <input type="hidden" name="station_id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($stations)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No stations on this floor yet.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (!empty($stations)): ?>
            <div class="card-footer" style="background: var(--bg-dark); border-top: 1px solid var(--border);">
                <form method="POST" class="d-flex gap-2 align-items-center">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_status">
                    <input type="hidden" name="station_ids" id="bulk-ids">
                    <select name="new_status" class="form-select form-select-sm" style="width:auto">
                        <option value="offline">Set Offline</option>
                        <option value="active">Set Active</option>
                        <option value="idle">Set Idle</option>
                        <option value="maintenance">Set Maintenance</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary" id="btn-bulk" disabled>Apply to Selected</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="xp-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-building fs-1 text-muted d-block mb-3"></i>
                <h4 class="text-white">No Floor Selected</h4>
                <p class="text-muted">Create a floor to get started</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddFloor">
                    <i class="bi bi-plus-lg me-1"></i> Create First Floor
                </button>
            </div>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="xp-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-building fs-1 text-muted d-block mb-3"></i>
                <h4 class="text-white">No Lab Selected</h4>
                <p class="text-muted">Create a lab to get started</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddLab">
                    <i class="bi bi-plus-lg me-1"></i> Create First Lab
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Lab Modal -->
    <div class="modal fade" id="modalAddLab" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_lab">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Lab</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Lab Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g., Main Computer Lab" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief description"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Building</label>
                        <input type="text" name="building" class="form-control" placeholder="e.g., Science Building">
                    </div>
                    <div class="row">
                        <div class="col-4 mb-3">
                            <label class="form-label">Floor Number</label>
                            <input type="number" name="floor_number" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Grid Columns</label>
                            <input type="number" name="grid_cols" class="form-control" value="6" min="2" max="12">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Grid Rows</label>
                            <input type="number" name="grid_rows" class="form-control" value="5" min="2" max="10">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Lab</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Floor Modal -->
    <div class="modal fade" id="modalAddFloor" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_floor">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Floor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Floor Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g., Room 101, Main Floor" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Building</label>
                        <input type="text" name="building" class="form-control" placeholder="e.g., Science Building">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Floor Number</label>
                        <input type="number" name="floor_number" class="form-control" value="1" min="1">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Grid Columns</label>
                            <input type="number" name="grid_cols" class="form-control" value="6" min="2" max="12">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Grid Rows</label>
                            <input type="number" name="grid_rows" class="form-control" value="5" min="2" max="10">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Floor</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Station Modal -->
    <div class="modal fade" id="modalAddStation" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_station">
                <div class="modal-header">
                    <h5 class="modal-title">Add Station</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Station Code *</label>
                        <input type="text" name="station_code" class="form-control" placeholder="e.g., PC-01, A1" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Row Label</label>
                            <input type="text" name="row_label" class="form-control" value="A" maxlength="5">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Column Number</label>
                            <input type="number" name="col_number" class="form-control" value="1" min="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Initial Status</label>
                        <select name="status" class="form-select">
                            <option value="offline">Offline</option>
                            <option value="idle">Idle</option>
                            <option value="active">Active</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Station</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Station Modal -->
    <div class="modal fade" id="modalEditStation" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_station">
                <input type="hidden" name="station_id" id="edit-id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Station</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Station Code *</label>
                        <input type="text" name="station_code" id="edit-code" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Row Label</label>
                            <input type="text" name="row_label" id="edit-row" class="form-control" maxlength="5">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Column Number</label>
                            <input type="number" name="col_number" id="edit-col" class="form-control" min="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit-status" class="form-select">
                            <option value="offline">Offline</option>
                            <option value="idle">Idle</option>
                            <option value="active">Active</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hostname</label>
                        <input type="text" name="hostname" id="edit-hostname" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">IP Address</label>
                        <input type="text" name="ip_address" id="edit-ip" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">MAC Address</label>
                        <input type="text" name="mac_address" id="edit-mac" class="form-control">
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
    function editStation(s) {
        document.getElementById('edit-id').value = s.id;
        document.getElementById('edit-code').value = s.station_code;
        document.getElementById('edit-row').value = s.row_label || '';
        document.getElementById('edit-col').value = s.col_number || 1;
        document.getElementById('edit-status').value = s.status || 'offline';
        document.getElementById('edit-hostname').value = s.hostname || '';
        document.getElementById('edit-ip').value = s.ip_address || '';
        document.getElementById('edit-mac').value = s.mac_address || '';
        new bootstrap.Modal(document.getElementById('modalEditStation')).show();
    }

    // Bulk selection
    document.getElementById('select-all')?.addEventListener('change', function() {
        document.querySelectorAll('.station-check').forEach(cb => cb.checked = this.checked);
        updateBulkBtn();
    });

    document.querySelectorAll('.station-check').forEach(cb => {
        cb.addEventListener('change', updateBulkBtn);
    });

    function updateBulkBtn() {
        const checked = [...document.querySelectorAll('.station-check:checked')].map(cb => cb.value);
        document.getElementById('bulk-ids').value = checked.join(',');
        document.getElementById('btn-bulk').disabled = checked.length === 0;
    }
    </script>
</body>
</html>