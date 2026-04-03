<?php
/**
 * XPLabs - Lab Monitoring Page (Computer Cafe Style)
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Services\LabService;

Auth::requireRole(['admin', 'teacher']);

$db = Database::getInstance();
$labService = new LabService();

$floors = $labService->getFloors();
$stations = $labService->getStations();
$stats = $labService->getStats();

// Group stations by floor
$stationsByFloor = [];
foreach ($stations as $s) {
    $fid = $s['floor_id'] ?? 0;
    if (!isset($stationsByFloor[$fid])) $stationsByFloor[$fid] = [];
    $stationsByFloor[$fid][] = $s;
}

$currentFloorId = $_GET['floor'] ?? ($floors[0]['id'] ?? null);
$currentFloorStations = $stationsByFloor[$currentFloorId] ?? [];
$currentFloor = null;
foreach ($floors as $f) { if ($f['id'] == $currentFloorId) { $currentFloor = $f; break; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Monitor - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --bg-panel: #1e293b;
            --border: #334155;
            --text: #e2e8f0;
            --text-muted: #94a3b8;
            --accent: #6366f1;
            --green: #22c55e;
            --yellow: #eab308;
            --red: #ef4444;
            --gray: #64748b;
            --orange: #f97316;
        }
        
        * { box-sizing: border-box; }
        
        body {
            background: var(--bg-dark);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            margin: 0;
            min-height: 100vh;
        }

        /* Top Bar */
        .topbar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .topbar-brand { display: flex; align-items: center; gap: 0.75rem; }
        .topbar-brand h5 { margin: 0; font-weight: 700; color: #fff; }
        .topbar-brand span { color: var(--text-muted); font-size: 0.85rem; }
        
        /* Stats Bar */
        .stats-bar {
            display: flex;
            gap: 1rem;
            padding: 1rem 1.5rem;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
        }
        .stat-chip {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--bg-dark);
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        .stat-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .stat-dot.green { background: var(--green); box-shadow: 0 0 8px var(--green); }
        .stat-dot.yellow { background: var(--yellow); }
        .stat-dot.red { background: var(--red); }
        .stat-dot.gray { background: var(--gray); }
        .stat-count { font-weight: 700; font-size: 1.1rem; color: #fff; }
        .stat-label { font-size: 0.75rem; color: var(--text-muted); }

        /* Floor Tabs */
        .floor-tabs {
            display: flex;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid var(--border);
            overflow-x: auto;
        }
        .floor-tab {
            padding: 0.5rem 1.25rem;
            border-radius: 6px;
            background: var(--bg-dark);
            border: 1px solid var(--border);
            color: var(--text-muted);
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .floor-tab:hover { border-color: var(--accent); color: var(--text); }
        .floor-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }

        /* Main Layout */
        .main-layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            height: calc(100vh - 180px);
        }

        /* View Toggle */
        .view-toggle {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .view-btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--bg-dark);
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .view-btn:hover { border-color: var(--accent); color: var(--text); }
        .view-btn.active { background: var(--accent); border-color: var(--accent); color: #fff; }

        /* Floor Plan Grid */
        .floor-plan {
            padding: 1.5rem;
            overflow: auto;
        }

        /* Seat Plan View */
        .seat-plan-view {
            display: none;
        }
        .seat-plan-view.active {
            display: block;
        }
        .card-view {
            display: block;
        }
        .card-view.hidden {
            display: none;
        }

        /* Board */
        .board-indicator {
            background: linear-gradient(135deg, #1a365d, #2d3748);
            border: 2px solid #4a5568;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            text-align: center;
            margin-bottom: 1.5rem;
            color: #e2e8f0;
            font-weight: 600;
        }

        /* Seat Plan Grid */
        .seat-plan-grid {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(var(--cols, 6), 1fr);
        }
        .seat-plan-cell {
            aspect-ratio: 1;
            border: 2px dashed var(--border);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100px;
            position: relative;
        }
        .seat-plan-cell.occupied {
            border-style: solid;
        }
        .seat-plan-card {
            width: 100%;
            height: 100%;
            background: var(--bg-card);
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 0.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .seat-plan-card:hover { transform: scale(1.02); border-color: var(--accent); }
        .seat-plan-card.active { border-color: var(--green); background: rgba(34, 197, 94, 0.1); }
        .seat-plan-card.idle { border-color: var(--yellow); background: rgba(234, 179, 8, 0.05); }
        .seat-plan-card.offline { border-color: var(--gray); opacity: 0.5; }
        .seat-plan-card.maintenance { border-color: var(--red); background: rgba(239, 68, 68, 0.1); }
        .seat-plan-card .seat-status {
            position: absolute; top: 4px; right: 4px;
            width: 8px; height: 8px; border-radius: 50%;
        }
        .seat-plan-card .seat-status.active { background: var(--green); box-shadow: 0 0 6px var(--green); animation: pulse 2s infinite; }
        .seat-plan-card .seat-status.idle { background: var(--yellow); }
        .seat-plan-card .seat-status.offline { background: var(--gray); }
        .seat-plan-card .seat-status.maintenance { background: var(--red); }
        .seat-plan-card .seat-icon { font-size: 1.25rem; margin-bottom: 0.25rem; }
        .seat-plan-card .seat-number { font-weight: 700; font-size: 0.7rem; color: #fff; }
        .seat-plan-card .seat-user { font-size: 0.6rem; color: var(--text-muted); }

        /* Teacher Desk */
        .teacher-desk-indicator {
            margin-top: 1.5rem;
            padding: 0.75rem;
            background: linear-gradient(135deg, #312e81, #4338ca);
            border: 2px solid var(--accent);
            border-radius: 8px;
            text-align: center;
            color: #fff;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .floor-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #fff;
        }
        .seat-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        }
        
        /* Seat Card */
        .seat-card {
            background: var(--bg-card);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            min-height: 160px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .seat-card:hover { transform: translateY(-2px); border-color: var(--accent); }
        .seat-card.active { border-color: var(--green); background: rgba(34, 197, 94, 0.1); }
        .seat-card.idle { border-color: var(--yellow); background: rgba(234, 179, 8, 0.05); }
        .seat-card.offline { border-color: var(--gray); opacity: 0.6; }
        .seat-card.maintenance { border-color: var(--red); background: rgba(239, 68, 68, 0.1); }
        
        .seat-status {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .seat-status.active { background: var(--green); box-shadow: 0 0 10px var(--green); animation: pulse 2s infinite; }
        .seat-status.idle { background: var(--yellow); }
        .seat-status.offline { background: var(--gray); }
        .seat-status.maintenance { background: var(--red); }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.2); }
        }
        
        .seat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .seat-number {
            font-weight: 700;
            font-size: 0.9rem;
            color: #fff;
            margin-bottom: 0.25rem;
        }
        .seat-user {
            font-size: 0.75rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        .seat-task {
            font-size: 0.7rem;
            color: var(--accent);
            margin-top: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        /* Side Panel */
        .side-panel {
            background: var(--bg-panel);
            border-left: 1px solid var(--border);
            overflow-y: auto;
            padding: 1rem;
        }
        .panel-section {
            margin-bottom: 1.5rem;
        }
        .panel-section h6 {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
        }
        
        /* Seat Detail Modal */
        .seat-detail {
            background: var(--bg-dark);
            border-radius: 8px;
            padding: 1rem;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border);
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: var(--text-muted); font-size: 0.85rem; }
        .detail-value { color: #fff; font-size: 0.85rem; font-weight: 500; }
        
        .action-btn {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg-card);
            color: var(--text);
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .action-btn:hover { border-color: var(--accent); background: rgba(99, 102, 241, 0.1); }
        .action-btn.danger:hover { border-color: var(--red); background: rgba(239, 68, 68, 0.1); }
        .action-btn.success:hover { border-color: var(--green); background: rgba(34, 197, 94, 0.1); }

        /* Filter Buttons */
        .filter-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-muted);
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filter-btn:hover, .filter-btn.active { background: var(--accent); border-color: var(--accent); color: #fff; }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include __DIR__ . '/components/admin_sidebar.php'; ?>

    <!-- Top Bar -->
    <div class="topbar" style="margin-left: 260px;">
        <div class="topbar-brand">
            <div>
                <h5><i class="bi bi-display me-2"></i>XPLabs Lab Monitor</h5>
                <span>Real-time Computer Lab Management</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= $_SESSION['user_role'] === 'admin' ? 'dashboard_admin.php' : 'dashboard_teacher.php' ?>" class="btn btn-sm btn-outline-light">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <button class="btn btn-sm btn-primary" id="btn-refresh">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-chip">
            <div class="stat-dot green"></div>
            <div>
                <div class="stat-count" id="stat-active"><?= $stats['active'] ?></div>
                <div class="stat-label">Active</div>
            </div>
        </div>
        <div class="stat-chip">
            <div class="stat-dot yellow"></div>
            <div>
                <div class="stat-count" id="stat-idle"><?= $stats['idle'] ?></div>
                <div class="stat-label">Idle</div>
            </div>
        </div>
        <div class="stat-chip">
            <div class="stat-dot gray"></div>
            <div>
                <div class="stat-count" id="stat-offline"><?= $stats['offline'] ?></div>
                <div class="stat-label">Offline</div>
            </div>
        </div>
        <div class="stat-chip">
            <div class="stat-dot red"></div>
            <div>
                <div class="stat-count" id="stat-maintenance"><?= $stats['maintenance'] ?></div>
                <div class="stat-label">Maintenance</div>
            </div>
        </div>
        <div class="stat-chip ms-auto">
            <div>
                <div class="stat-count"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Stations</div>
            </div>
        </div>
    </div>

    <!-- Floor Tabs -->
    <div class="floor-tabs">
        <?php foreach ($floors as $floor): ?>
        <a href="?floor=<?= $floor['id'] ?>" class="floor-tab <?= $floor['id'] == $currentFloorId ? 'active' : '' ?>">
            <?= e($floor['name']) ?>
        </a>
        <?php endforeach; ?>
        <?php if (empty($floors)): ?>
        <span class="text-muted small">No lab floors configured yet</span>
        <?php endif; ?>
    </div>

    <!-- Main Layout -->
    <div class="main-layout">
        <!-- Floor Plan -->
        <div class="floor-plan">
            <?php if ($currentFloor): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="floor-title mb-0"><?= e($currentFloor['name']) ?> - <?= count($currentFloorStations) ?> Stations</div>
                <!-- View Toggle -->
                <div class="view-toggle">
                    <button class="view-btn active" data-view="cards" onclick="switchView('cards')">
                        <i class="bi bi-grid-3x3-gap"></i> Cards
                    </button>
                    <button class="view-btn" data-view="plan" onclick="switchView('plan')">
                        <i class="bi bi-layout-text-window-reverse"></i> Seat Plan
                    </button>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filter-bar">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="active">Active</button>
                <button class="filter-btn" data-filter="idle">Idle</button>
                <button class="filter-btn" data-filter="offline">Offline</button>
                <button class="filter-btn" data-filter="maintenance">Maintenance</button>
            </div>

            <!-- Card View -->
            <div class="card-view" id="card-view">
            <!-- Seat Grid -->
            <div class="seat-grid" id="seat-grid">
                <?php foreach ($currentFloorStations as $station): 
                    $status = $station['status'] ?? 'offline';
                    $user = trim(($station['first_name'] ?? '') . ' ' . ($station['last_name'] ?? ''));
                    $task = $station['task'] ?? '';
                ?>
                <div class="seat-card <?= $status ?>" data-status="<?= $status ?>" data-station-id="<?= $station['id'] ?>" data-user="<?= e($user) ?>" data-task="<?= e($task) ?>">
                    <div class="seat-status <?= $status ?>"></div>
                    <div class="seat-icon">
                        <?php if ($status === 'active'): ?>
                            <i class="bi bi-pc-display"></i>
                        <?php elseif ($status === 'idle'): ?>
                            <i class="bi bi-pc-display-horizontal"></i>
                        <?php elseif ($status === 'maintenance'): ?>
                            <i class="bi bi-tools"></i>
                        <?php else: ?>
                            <i class="bi bi-pc-display text-muted"></i>
                        <?php endif; ?>
                    </div>
                    <div class="seat-number"><?= e($station['station_code']) ?></div>
                    <div class="seat-user"><?= $user ?: 'Available' ?></div>
                    <?php if ($task): ?>
                    <div class="seat-task" title="<?= e($task) ?>"><?= e($task) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($currentFloorStations)): ?>
                <div class="empty-state col-12">
                    <i class="bi bi-display"></i>
                    <p>No stations configured for this floor</p>
                    <a href="admin_system.php" class="btn btn-sm btn-primary">Add Stations</a>
                </div>
                <?php endif; ?>
            </div>
            </div>

            <!-- Seat Plan View -->
            <div class="seat-plan-view" id="seat-plan-view">
                <!-- Board -->
                <div class="board-indicator">
                    <i class="bi bi-display me-2"></i> Front of Room - Board & Projector
                </div>

                <!-- Seat Plan Grid -->
                <?php 
                $planCols = (int) ($currentFloor['grid_cols'] ?? 6);
                $planRows = (int) ($currentFloor['grid_rows'] ?? 5);
                ?>
                <div class="seat-plan-grid" id="seat-plan-grid" style="--cols: <?= $planCols ?>">
                    <?php
                    $stationMap = [];
                    foreach ($currentFloorStations as $s) {
                        $row = $s['row_label'] ?? 'A';
                        $col = (int) ($s['col_number'] ?? 1);
                        $key = $row . '-' . $col;
                        $stationMap[$key] = $s;
                    }
                    
                    $rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'];
                    $gridRows = $planRows;
                    $gridCols = $planCols;
                    for ($r = 0; $r < $gridRows; $r++) {
                        for ($c = 1; $c <= $gridCols; $c++) {
                            $rowLabel = $rows[$r] ?? ($r + 1);
                            $key = $rowLabel . '-' . $c;
                            $station = $stationMap[$key] ?? null;
                    ?>
                    <div class="seat-plan-cell <?= $station ? 'occupied' : '' ?>" data-row="<?= $rowLabel ?>" data-col="<?= $c ?>">
                        <?php if ($station): 
                            $status = $station['status'] ?? 'offline';
                            $user = trim(($station['first_name'] ?? '') . ' ' . ($station['last_name'] ?? ''));
                        ?>
                        <div class="seat-plan-card <?= $status ?>" data-station-id="<?= $station['id'] ?>" data-user="<?= e($user) ?>" data-task="<?= e($station['task'] ?? '') ?>">
                            <div class="seat-status <?= $status ?>"></div>
                            <div class="seat-icon">
                                <?php if ($status === 'active'): ?>
                                    <i class="bi bi-pc-display text-success"></i>
                                <?php elseif ($status === 'idle'): ?>
                                    <i class="bi bi-pc-display-horizontal text-warning"></i>
                                <?php elseif ($status === 'maintenance'): ?>
                                    <i class="bi bi-tools text-danger"></i>
                                <?php else: ?>
                                    <i class="bi bi-pc-display text-secondary"></i>
                                <?php endif; ?>
                            </div>
                            <div class="seat-number"><?= e($station['station_code']) ?></div>
                            <div class="seat-user"><?= $user ?: 'Available' ?></div>
                        </div>
                        <?php else: ?>
                        <span class="text-muted small"><?= $rowLabel ?><?= $c ?></span>
                        <?php endif; ?>
                    </div>
                    <?php
                        }
                    }
                    ?>
                </div>

                <!-- Teacher Desk -->
                <div class="teacher-desk-indicator">
                    <i class="bi bi-person-workspace me-2"></i> Teacher's Desk
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-building"></i>
                <p>Select a lab floor to view</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Side Panel -->
        <div class="side-panel">
            <div class="panel-section">
                <h6>Station Details</h6>
                <div id="station-detail">
                    <div class="text-center text-muted py-4 small">
                        <i class="bi bi-hand-index fs-3 d-block mb-2"></i>
                        Click a station to view details
                    </div>
                </div>
            </div>

            <div class="panel-section">
                <h6>Quick Actions</h6>
                <button class="action-btn" onclick="refreshData()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh Data
                </button>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <a href="admin_system.php" class="action-btn text-decoration-none">
                    <i class="bi bi-gear"></i> Manage Lab
                </a>
                <?php endif; ?>
            </div>

            <div class="panel-section">
                <h6>Legend</h6>
                <div class="d-flex flex-column gap-2 small">
                    <div class="d-flex align-items-center gap-2">
                        <div class="stat-dot green"></div>
                        <span>Active - Student logged in</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="stat-dot yellow"></div>
                        <span>Idle - No activity</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="stat-dot gray"></div>
                        <span>Offline - Station unavailable</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="stat-dot red"></div>
                        <span>Maintenance - Under repair</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Station detail panel
    document.querySelectorAll('.seat-card').forEach(card => {
        card.addEventListener('click', function() {
            const id = this.dataset.stationId;
            const status = this.dataset.status;
            const user = this.dataset.user || 'None';
            const task = this.dataset.task || 'None';
            const number = this.querySelector('.seat-number').textContent;
            
            const statusColors = {
                active: 'text-success',
                idle: 'text-warning',
                offline: 'text-secondary',
                maintenance: 'text-danger'
            };
            
            document.getElementById('station-detail').innerHTML = `
                <div class="seat-detail">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-pc-display fs-4"></i>
                        <strong>${number}</strong>
                        <span class="badge bg-${status === 'active' ? 'success' : status === 'idle' ? 'warning' : status === 'maintenance' ? 'danger' : 'secondary'} ms-auto">${status}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Station ID</span>
                        <span class="detail-value">#${id}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status</span>
                        <span class="detail-value ${statusColors[status] || ''}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">User</span>
                        <span class="detail-value">${user}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Task</span>
                        <span class="detail-value">${task}</span>
                    </div>
                </div>
            `;
            
            // Highlight selected
            document.querySelectorAll('.seat-card').forEach(c => c.style.outline = 'none');
            this.style.outline = '2px solid var(--accent)';
        });
    });

    // Filter stations
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const filter = this.dataset.filter;
            
            document.querySelectorAll('.seat-card').forEach(card => {
                if (filter === 'all' || card.dataset.status === filter) {
                    card.style.display = '';
                    card.style.opacity = '1';
                } else {
                    card.style.opacity = '0.2';
                }
            });
        });
    });

    // Refresh data
    async function refreshData() {
        const btn = document.getElementById('btn-refresh');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Refreshing...';
        btn.disabled = true;
        
        try {
            const response = await fetch('api/lab/stations.php');
            const data = await response.json();
            if (data.stations) {
                // Update stats
                const stats = { active: 0, idle: 0, offline: 0, maintenance: 0 };
                data.stations.forEach(s => { if (stats[s.status] !== undefined) stats[s.status]++; });
                
                document.getElementById('stat-active').textContent = stats.active;
                document.getElementById('stat-idle').textContent = stats.idle;
                document.getElementById('stat-offline').textContent = stats.offline;
                document.getElementById('stat-maintenance').textContent = stats.maintenance;
            }
        } catch (e) {
            console.warn('Refresh failed:', e);
        }
        
        btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Refresh';
        btn.disabled = false;
    }

    // Switch view (cards vs seat plan)
    function switchView(view) {
        document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`.view-btn[data-view="${view}"]`).classList.add('active');
        
        if (view === 'cards') {
            document.getElementById('card-view').classList.remove('hidden');
            document.getElementById('seat-plan-view').classList.remove('active');
        } else {
            document.getElementById('card-view').classList.add('hidden');
            document.getElementById('seat-plan-view').classList.add('active');
        }
    }

    // Seat plan card clicks
    document.querySelectorAll('.seat-plan-card').forEach(card => {
        card.addEventListener('click', function() {
            const id = this.dataset.stationId;
            const status = this.dataset.status || this.className.match(/(active|idle|offline|maintenance)/)?.[1] || 'offline';
            const user = this.dataset.user || 'None';
            const task = this.dataset.task || 'None';
            const number = this.querySelector('.seat-number')?.textContent || '';
            
            const statusColors = {
                active: 'text-success',
                idle: 'text-warning',
                offline: 'text-secondary',
                maintenance: 'text-danger'
            };
            
            document.getElementById('station-detail').innerHTML = `
                <div class="seat-detail">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-pc-display fs-4"></i>
                        <strong>${number}</strong>
                        <span class="badge bg-${status === 'active' ? 'success' : status === 'idle' ? 'warning' : status === 'maintenance' ? 'danger' : 'secondary'} ms-auto">${status}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Station ID</span>
                        <span class="detail-value">#${id}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status</span>
                        <span class="detail-value ${statusColors[status] || ''}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">User</span>
                        <span class="detail-value">${user}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Task</span>
                        <span class="detail-value">${task}</span>
                    </div>
                </div>
            `;
        });
    });

    // Auto-refresh every 10 seconds
    setInterval(refreshData, 10000);
    </script>
</body>
</html>