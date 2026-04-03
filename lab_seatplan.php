<?php
/**
 * XPLabs - Lab Seat Plan Editor (Drag & Drop Arrangement)
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Services\LabService;

Auth::requireRole(['admin', 'teacher']);

$labService = new LabService();
$floors = $labService->getFloors();
$stations = $labService->getStations();

// Group stations by floor
$stationsByFloor = [];
foreach ($stations as $s) {
    $fid = $s['floor_id'] ?? 0;
    if (!isset($stationsByFloor[$fid])) $stationsByFloor[$fid] = [];
    $stationsByFloor[$fid][] = $s;
}

$currentFloorId = (int) ($_GET['floor'] ?? ($floors[0]['id'] ?? 0));
$currentFloorStations = $stationsByFloor[$currentFloorId] ?? [];
$currentFloor = null;
foreach ($floors as $f) { if ($f['id'] == $currentFloorId) { $currentFloor = $f; break; } }

// Handle POST for saving layout
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'save_layout' && !empty($_POST['layout'])) {
            $layout = json_decode($_POST['layout'], true);
            if ($layout && $currentFloorId) {
                $labService->saveFloorLayout($currentFloorId, $layout);
                $message = ['type' => 'success', 'text' => 'Layout saved successfully'];
                // Refresh ALL data including floor grid settings
                $currentFloor = $labService->getFloor($currentFloorId);
                $stations = $labService->getStations();
                $floors = $labService->getFloors();
                $stationsByFloor = [];
                foreach ($stations as $s) {
                    $fid = $s['floor_id'] ?? 0;
                    if (!isset($stationsByFloor[$fid])) $stationsByFloor[$fid] = [];
                    $stationsByFloor[$fid][] = $s;
                }
                $currentFloorStations = $stationsByFloor[$currentFloorId] ?? [];
            }
        } elseif ($action === 'add_station' && $currentFloorId) {
            $labService->createStation([
                'floor_id' => $currentFloorId,
                'station_code' => $_POST['station_code'] ?? 'PC-NEW',
                'row_label' => $_POST['row_label'] ?? 'A',
                'col_number' => (int) ($_POST['col_number'] ?? 1),
                'status' => 'offline',
            ]);
            $message = ['type' => 'success', 'text' => 'Station added'];
            // Refresh
            $stations = $labService->getStations();
            $stationsByFloor = [];
            foreach ($stations as $s) {
                $fid = $s['floor_id'] ?? 0;
                if (!isset($stationsByFloor[$fid])) $stationsByFloor[$fid] = [];
                $stationsByFloor[$fid][] = $s;
            }
            $currentFloorStations = $stationsByFloor[$currentFloorId] ?? [];
        }
    } catch (\Exception $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}

// Count statuses
$statusCounts = ['active' => 0, 'idle' => 0, 'offline' => 0, 'maintenance' => 0];
foreach ($currentFloorStations as $s) {
    $st = $s['status'] ?? 'offline';
    if (isset($statusCounts[$st])) $statusCounts[$st]++;
}

$gridCols = $currentFloor['grid_cols'] ?? 6;
$gridRows = $currentFloor['grid_rows'] ?? 5;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seat Plan Editor - XPLabs</title>
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
            --gray: #94a3b8;
        }
        
        body {
            background: var(--bg-main);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }
        .main-content { margin-left: 260px; padding: 2rem; }

        /* Toolbar */
        .toolbar {
            display: flex; gap: 0.5rem; flex-wrap: wrap;
            padding: 1rem; background: var(--bg-card);
            border: 1px solid var(--border); border-radius: 12px;
            margin-bottom: 1rem;
        }
        .toolbar .btn { font-size: 0.85rem; }

        /* Floor Plan */
        .floor-plan {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            position: relative;
        }

        /* Board */
        .board {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            border: 2px solid #a5b4fc;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            text-align: center;
            margin-bottom: 1.5rem;
            color: #312e81;
            font-weight: 600;
        }

        /* Grid */
        .seat-grid {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(var(--cols, 6), 1fr);
            min-height: 300px;
        }

        /* Grid Cell */
        .grid-cell {
            aspect-ratio: 1;
            border: 2px dashed var(--border);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 120px;
            transition: all 0.2s;
            position: relative;
        }
        .grid-cell.drag-over {
            border-color: var(--accent);
            background: rgba(99, 102, 241, 0.1);
        }
        .grid-cell.has-station {
            border-style: solid;
        }

        /* Station Card */
        .station-card {
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
            cursor: grab;
            transition: all 0.2s;
            position: relative;
        }
        .station-card:active { cursor: grabbing; }
        .station-card:hover { transform: scale(1.02); border-color: var(--accent); }
        .station-card.active { border-color: var(--green); }
        .station-card.idle { border-color: var(--yellow); }
        .station-card.offline { border-color: var(--gray); opacity: 0.6; }
        .station-card.maintenance { border-color: var(--red); }
        
        .station-status {
            position: absolute; top: 4px; right: 4px;
            width: 8px; height: 8px; border-radius: 50%;
        }
        .station-status.active { background: var(--green); box-shadow: 0 0 6px var(--green); }
        .station-status.idle { background: var(--yellow); }
        .station-status.offline { background: var(--gray); }
        .station-status.maintenance { background: var(--red); }
        
        .station-icon { font-size: 1.5rem; margin-bottom: 0.25rem; }
        .station-code { font-weight: 700; font-size: 0.75rem; color: var(--text); }
        .station-user { font-size: 0.65rem; color: var(--text-muted); }

        /* Empty cell */
        .empty-cell {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        /* Stats */
        .stat-chip {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.5rem 1rem; background: var(--bg-card);
            border-radius: 8px; border: 1px solid var(--border);
        }
        .stat-dot { width: 10px; height: 10px; border-radius: 50%; }
        .stat-dot.green { background: var(--green); box-shadow: 0 0 8px var(--green); }
        .stat-dot.yellow { background: var(--yellow); }
        .stat-dot.gray { background: var(--gray); }
        .stat-dot.red { background: var(--red); }
        .stat-count { font-weight: 700; font-size: 1.1rem; color: var(--text); }
        .stat-label { font-size: 0.75rem; color: var(--text-muted); }

        /* Floor tabs */
        .floor-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        .floor-tab {
            padding: 0.5rem 1.25rem; border-radius: 6px;
            background: var(--bg-card); border: 1px solid var(--border);
            color: var(--text-muted); text-decoration: none;
            transition: all 0.2s;
        }
        .floor-tab:hover { border-color: var(--accent); color: var(--text); }
        .floor-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }

        /* Form */
        .form-control, .form-select {
            background: var(--bg-card); border: 1px solid var(--border); color: var(--text);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent); box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }

        /* Teacher desk */
        .teacher-desk {
            margin-top: 1.5rem;
            padding: 1rem;
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            border: 2px solid #a5b4fc;
            border-radius: 8px;
            text-align: center;
            color: #312e81;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include __DIR__ . '/components/admin_sidebar.php'; ?>
<!-- Main -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-layout-text-window-reverse me-2"></i>Seat Plan Editor</h2>
                <p class="text-muted mb-0">Drag and drop stations to arrange the floor layout</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-light btn-sm" onclick="saveLayout()">
                    <i class="bi bi-save me-1"></i> Save Layout
                </button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddStation">
                    <i class="bi bi-plus-lg me-1"></i> Add Station
                </button>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show">
            <?= e($message['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Hidden form for CSRF token -->
        <form id="csrf-form" class="d-none">
            <?= csrf_field() ?>
        </form>

        <!-- Stats -->
        <div class="d-flex flex-wrap gap-2 mb-4">
            <div class="stat-chip">
                <div class="stat-dot green"></div>
                <div><div class="stat-count"><?= $statusCounts['active'] ?></div><div class="stat-label">Active</div></div>
            </div>
            <div class="stat-chip">
                <div class="stat-dot yellow"></div>
                <div><div class="stat-count"><?= $statusCounts['idle'] ?></div><div class="stat-label">Idle</div></div>
            </div>
            <div class="stat-chip">
                <div class="stat-dot gray"></div>
                <div><div class="stat-count"><?= $statusCounts['offline'] ?></div><div class="stat-label">Offline</div></div>
            </div>
            <div class="stat-chip">
                <div class="stat-dot red"></div>
                <div><div class="stat-count"><?= $statusCounts['maintenance'] ?></div><div class="stat-label">Maintenance</div></div>
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
            <span class="text-muted small">No lab floors configured</span>
            <?php endif; ?>
        </div>

        <?php if ($currentFloor): ?>
        <!-- Toolbar -->
        <div class="toolbar">
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small">Columns:</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="changeCols(-1)"><i class="bi bi-dash"></i></button>
                <span class="text-white fw-bold" id="col-count"><?= $gridCols ?></span>
                <button class="btn btn-sm btn-outline-secondary" onclick="changeCols(1)"><i class="bi bi-plus"></i></button>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small">Rows:</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="changeRows(-1)"><i class="bi bi-dash"></i></button>
                <span class="text-white fw-bold" id="row-count"><?= $gridRows ?></span>
                <button class="btn btn-sm btn-outline-secondary" onclick="changeRows(1)"><i class="bi bi-plus"></i></button>
            </div>
            <div class="ms-auto">
                <span class="text-muted small"><?= count($currentFloorStations) ?> stations placed</span>
            </div>
        </div>

        <!-- Floor Plan -->
        <div class="floor-plan">
            <!-- Board -->
            <div class="board">
                <i class="bi bi-display me-2"></i> Front of Room - Board & Projector
            </div>

            <!-- Grid -->
            <div class="seat-grid" id="seat-grid" style="--cols: <?= $gridCols ?>">
                <?php
                // Build grid based on floor dimensions
                $stationMap = [];
                foreach ($currentFloorStations as $s) {
                    $row = $s['row_label'] ?? 'A';
                    $col = (int) ($s['col_number'] ?? 1);
                    $key = $row . '-' . $col;
                    $stationMap[$key] = $s;
                }
                
                $rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
                for ($r = 0; $r < $gridRows; $r++) {
                    for ($c = 1; $c <= $gridCols; $c++) {
                        $rowLabel = $rows[$r] ?? ($r + 1);
                        $key = $rowLabel . '-' . $c;
                        $station = $stationMap[$key] ?? null;
                ?>
                <div class="grid-cell <?= $station ? 'has-station' : '' ?>" data-row="<?= $rowLabel ?>" data-col="<?= $c ?>">
                    <?php if ($station): 
                        $status = $station['status'] ?? 'offline';
                        $user = trim(($station['first_name'] ?? '') . ' ' . ($station['last_name'] ?? ''));
                    ?>
                    <div class="station-card <?= $status ?>" draggable="true" 
                         data-station-id="<?= $station['id'] ?>"
                         data-row="<?= $rowLabel ?>" data-col="<?= $c ?>">
                        <div class="station-status <?= $status ?>"></div>
                        <div class="station-icon">
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
                        <div class="station-code"><?= e($station['station_code']) ?></div>
                        <div class="station-user"><?= $user ?: 'Available' ?></div>
                    </div>
                    <?php else: ?>
                    <div class="empty-cell">
                        <span><?= $rowLabel ?><?= $c ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php
                    }
                }
                ?>
            </div>

            <!-- Teacher Desk -->
            <div class="teacher-desk">
                <i class="bi bi-person-workspace me-2"></i> Teacher's Desk
            </div>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-building fs-1 text-muted d-block mb-3"></i>
            <h4 class="text-white">No Floor Selected</h4>
            <p class="text-muted">Create a lab floor first in Lab Settings</p>
            <a href="admin_system.php" class="btn btn-primary">Go to Lab Settings</a>
        </div>
        <?php endif; ?>
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
                        <input type="text" name="station_code" class="form-control" placeholder="e.g., PC-01" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Row</label>
                            <select name="row_label" class="form-select">
                                <?php for ($r = 0; $r < $gridRows; $r++): ?>
                                <option value="<?= chr(65 + $r) ?>"><?= chr(65 + $r) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Column</label>
                            <select name="col_number" class="form-select">
                                <?php for ($c = 1; $c <= $gridCols; $c++): ?>
                                <option value="<?= $c ?>"><?= $c ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Station</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let draggedCard = null;
    let draggedData = null;

    // Drag and Drop
    document.querySelectorAll('.station-card').forEach(card => {
        card.addEventListener('dragstart', function(e) {
            draggedCard = this;
            draggedData = {
                stationId: this.dataset.stationId,
                fromRow: this.dataset.row,
                fromCol: this.dataset.col
            };
            this.style.opacity = '0.5';
            e.dataTransfer.effectAllowed = 'move';
        });

        card.addEventListener('dragend', function() {
            this.style.opacity = '1';
            draggedCard = null;
            draggedData = null;
        });
    });

    document.querySelectorAll('.grid-cell').forEach(cell => {
        cell.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.classList.add('drag-over');
        });

        cell.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });

        cell.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            if (!draggedCard || !draggedData) return;
            
            const toRow = this.dataset.row;
            const toCol = this.dataset.col;
            
            // Check if target cell has a station
            const existingCard = this.querySelector('.station-card');
            if (existingCard && existingCard !== draggedCard) {
                // Swap stations
                const existingData = {
                    stationId: existingCard.dataset.stationId,
                    row: existingCard.dataset.row,
                    col: existingCard.dataset.col
                };
                
                // Move dragged to target
                moveStation(draggedData.stationId, toRow, toCol);
                // Move existing to dragged's old position
                moveStation(existingData.stationId, draggedData.fromRow, draggedData.fromCol);
            } else if (!existingCard) {
                // Move to empty cell
                moveStation(draggedData.stationId, toRow, toCol);
            }
        });
    });

    function moveStation(stationId, row, col) {
        const card = document.querySelector(`.station-card[data-station-id="${stationId}"]`);
        if (!card) return;
        
        const parentCell = card.closest('.grid-cell');
        const oldRow = card.dataset.row;
        const oldCol = card.dataset.col;
        
        // Update card data
        card.dataset.row = row;
        card.dataset.col = col;
        
        // Find target cell
        const targetCell = document.querySelector(`.grid-cell[data-row="${row}"][data-col="${col}"]`);
        if (targetCell) {
            targetCell.classList.add('has-station');
            targetCell.innerHTML = '';
            targetCell.appendChild(card);
            
            // Update empty cell label
            const emptyLabel = targetCell.querySelector('.empty-cell span');
            if (emptyLabel) emptyLabel.textContent = row + col;
        }
        
        // Clear old cell if empty
        if (parentCell && parentCell !== targetCell) {
            if (!parentCell.querySelector('.station-card')) {
                parentCell.classList.remove('has-station');
                parentCell.innerHTML = `<div class="empty-cell"><span>${oldRow}${oldCol}</span></div>`;
            }
        }
    }

    let currentCols = <?= $gridCols ?>;
    let currentRows = <?= $gridRows ?>;

    function changeCols(delta) {
        currentCols = Math.max(2, Math.min(15, currentCols + delta));
        document.getElementById('seat-grid').style.setProperty('--cols', currentCols);
        document.getElementById('col-count').textContent = currentCols;
        rebuildGrid();
    }

    function changeRows(delta) {
        currentRows = Math.max(1, Math.min(15, currentRows + delta));
        document.getElementById('row-count').textContent = currentRows;
        rebuildGrid();
    }

    function rebuildGrid() {
        const grid = document.getElementById('seat-grid');
        const rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'];
        let html = '';
        
        for (let r = 0; r < currentRows; r++) {
            for (let c = 1; c <= currentCols; c++) {
                const rowLabel = rows[r] || (r + 1);
                const key = rowLabel + '-' + c;
                const existingCard = document.querySelector(`.station-card[data-row="${rowLabel}"][data-col="${c}"]`);
                
                if (existingCard) {
                    html += `<div class="grid-cell has-station" data-row="${rowLabel}" data-col="${c}">`;
                    html += existingCard.outerHTML;
                    html += `</div>`;
                } else {
                    html += `<div class="grid-cell" data-row="${rowLabel}" data-col="${c}">`;
                    html += `<div class="empty-cell"><span>${rowLabel}${c}</span></div>`;
                    html += `</div>`;
                }
            }
        }
        
        grid.innerHTML = html;
        grid.style.setProperty('--cols', currentCols);
        reinitDragDrop();
    }

    function reinitDragDrop() {
        document.querySelectorAll('.station-card').forEach(card => {
            card.addEventListener('dragstart', function(e) {
                draggedCard = this;
                draggedData = {
                    stationId: this.dataset.stationId,
                    fromRow: this.dataset.row,
                    fromCol: this.dataset.col
                };
                this.style.opacity = '0.5';
                e.dataTransfer.effectAllowed = 'move';
            });

            card.addEventListener('dragend', function() {
                this.style.opacity = '1';
                draggedCard = null;
                draggedData = null;
            });
        });

        document.querySelectorAll('.grid-cell').forEach(cell => {
            cell.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                this.classList.add('drag-over');
            });

            cell.addEventListener('dragleave', function() {
                this.classList.remove('drag-over');
            });

            cell.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
                
                if (!draggedCard || !draggedData) return;
                
                const toRow = this.dataset.row;
                const toCol = this.dataset.col;
                
                const existingCard = this.querySelector('.station-card');
                if (existingCard && existingCard !== draggedCard) {
                    moveStation(draggedData.stationId, toRow, toCol);
                    moveStation(existingCard.dataset.stationId, draggedData.fromRow, draggedData.fromCol);
                } else if (!existingCard) {
                    moveStation(draggedData.stationId, toRow, toCol);
                }
            });
        });
    }

    function saveLayout() {
        const layout = {
            grid: {
                cols: currentCols,
                rows: currentRows
            },
            stations: []
        };
        
        document.querySelectorAll('.station-card').forEach(card => {
            layout.stations.push({
                id: parseInt(card.dataset.stationId),
                row_label: card.dataset.row,
                col_number: parseInt(card.dataset.col)
            });
        });
        
        // Get CSRF token from PHP
        const csrfToken = '<?= csrf_token() ?>';
        
        // Submit via form
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="save_layout">
            <input type="hidden" name="layout" value='${JSON.stringify(layout)}'>
            <input type="hidden" name="csrf_token" value="${csrfToken}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
    </script>
</body>
</html>