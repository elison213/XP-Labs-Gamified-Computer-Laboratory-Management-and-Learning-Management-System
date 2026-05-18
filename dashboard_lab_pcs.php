<?php
/**
 * XPLabs - Lab PC Management Dashboard
 * For teachers to monitor and control lab PCs.
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Services\PCService;
use XPLabs\Services\LabService;
Auth::requireRole(['admin', 'teacher']);

$appConfig = require __DIR__ . '/config/app.php';
$labDashboardConfig = $appConfig['lab_dashboard'] ?? [];
$showKioskUi = !empty($labDashboardConfig['show_kiosk_ui']);
$showAutoDeployUi = !empty($labDashboardConfig['show_auto_deploy_ui']);
$autoDeployConfig = $appConfig['pc_auto_deploy'] ?? [];
$deploySummary = [];

$db = Database::getInstance();
$pcService = new PCService();
$labService = new LabService();
$currentRole = Auth::role();
$isAdmin = $currentRole === 'admin';

$floors = $labService->getFloors();
$selectedFloor = (int) ($_GET['floor_id'] ?? ($floors[0]['id'] ?? 0));
$stats = $pcService->getStats($selectedFloor);
if ($showAutoDeployUi) {
    $deploySummary = $pcService->getDeploymentStatusSummary();
}
$activeSessions = $pcService->getActiveSessions($selectedFloor);
$unassignedPcs = $pcService->getUnassignedPcs();
$stationsForFloor = $db->fetchAll(
    "SELECT id, station_code, floor_id FROM lab_stations WHERE floor_id = ? ORDER BY station_code ASC",
    [$selectedFloor]
);
$kioskDevices = [];
$kioskDeviceService = null;
if ($showKioskUi) {
    $kioskDeviceService = new \XPLabs\Services\KioskDeviceService();
    $kioskDevices = $kioskDeviceService->tableExists() ? $kioskDeviceService->listDevices() : [];
}

$pcs = $db->fetchAll(
    "SELECT lp.*, lf.name as floor_name,
            CASE
                WHEN lp.last_heartbeat IS NULL THEN 'offline'
                WHEN lp.last_heartbeat < DATE_SUB(NOW(), INTERVAL 300 SECOND) THEN 'offline'
                ELSE lp.status
            END AS effective_status
     FROM lab_pcs lp
     LEFT JOIN lab_floors lf ON lp.floor_id = lf.id
     WHERE lp.floor_id = ? OR ? = 0
     ORDER BY lp.hostname ASC",
    [$selectedFloor, $selectedFloor]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab PC Management - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --border: #334155;
            --text: #e2e8f0;
            --text-muted: #94a3b8;
            --green: #22c55e;
            --yellow: #eab308;
            --red: #ef4444;
            --blue: #3b82f6;
            --gray: #64748b;
        }
        body {
            background: var(--bg-dark);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
        }
        .navbar-custom {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 1.5rem;
        }
        .navbar-custom .toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
        }
        .stat-card .value { font-size: 2rem; font-weight: 700; color: #fff; }
        .stat-card .label { font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; }
        .stat-card.online .value { color: var(--green); }
        .stat-card.idle .value { color: var(--yellow); }
        .stat-card.locked .value { color: var(--red); }
        .stat-card.offline .value { color: var(--gray); }
        .pc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .pc-card {
            background: var(--bg-card);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 1rem;
            transition: all 0.3s;
            min-width: 0;
            overflow: hidden;
        }
        .pc-card.online { border-color: var(--green); }
        .pc-card.idle { border-color: var(--yellow); }
        .pc-card.locked { border-color: var(--red); }
        .pc-card.offline { border-color: var(--gray); opacity: 0.6; }
        .pc-card.maintenance { border-color: var(--red); background: rgba(239, 68, 68, 0.1); }
        .pc-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        .pc-name { font-weight: 700; font-size: 0.9rem; }
        .pc-status {
            font-size: 0.65rem;
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .pc-status.online { background: rgba(34, 197, 94, 0.2); color: var(--green); }
        .pc-status.idle { background: rgba(234, 179, 8, 0.2); color: var(--yellow); }
        .pc-status.locked { background: rgba(239, 68, 68, 0.2); color: var(--red); }
        .pc-status.offline { background: rgba(100, 116, 139, 0.2); color: var(--gray); }
        .pc-user {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pc-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.4rem;
            margin-top: 0.75rem;
        }
        .pc-actions button {
            min-width: 0;
            padding: 0.4rem 0.35rem;
            font-size: 0.68rem;
            line-height: 1.2;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg-dark);
            color: var(--text);
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pc-actions button:hover {
            border-color: var(--blue);
            background: rgba(59, 130, 246, 0.1);
        }
        .pc-actions button.lock-btn:hover { border-color: var(--red); background: rgba(239, 68, 68, 0.1); }
        .pc-actions button.unlock-btn:hover { border-color: var(--green); background: rgba(34, 197, 94, 0.1); }
        .pc-actions button.restart-btn:hover { border-color: #f59e0b; background: rgba(245, 158, 11, 0.12); }
        .floor-selector { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        .floor-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            font-weight: 600;
        }
        .floor-btn.active { border-color: var(--blue); background: rgba(59, 130, 246, 0.1); color: var(--blue); }
        .session-table { width: 100%; }
        .session-table th, .session-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
        }
        .session-table th { color: var(--text-muted); font-weight: 600; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar-custom d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <a href="<?= $isAdmin ? 'dashboard_admin.php' : 'dashboard_teacher.php' ?>" class="text-decoration-none" style="color: var(--text);">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <h5 class="mb-0" style="color: var(--text);">🖥️ Lab PC Management</h5>
        </div>
        <div class="toolbar-actions">
            <button class="btn btn-sm btn-outline-light" onclick="refreshPCs()">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
            <button class="btn btn-sm btn-warning" onclick="discoverPCs()">
                <i class="bi bi-router"></i> Discover PCs
            </button>
            <button class="btn btn-sm btn-danger" onclick="lockAllPCs()">
                <i class="bi bi-lock"></i> Lock All
            </button>
            <button class="btn btn-sm btn-success" onclick="unlockAllPCs()">
                <i class="bi bi-unlock"></i> Unlock All
            </button>
        </div>
    </nav>

    <div class="container-fluid p-4">
        <!-- Floor Selector -->
        <?php if (count($floors) > 1): ?>
        <div class="floor-selector">
            <?php foreach ($floors as $floor): ?>
            <a href="?floor_id=<?= $floor['id'] ?>" class="floor-btn <?= $floor['id'] == $selectedFloor ? 'active' : '' ?>"><?= e($floor['name']) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col">
                <div class="stat-card online">
                    <div class="value"><?= $stats['online'] ?></div>
                    <div class="label">Online</div>
                </div>
            </div>
            <div class="col">
                <div class="stat-card idle">
                    <div class="value"><?= $stats['idle'] ?></div>
                    <div class="label">Idle</div>
                </div>
            </div>
            <div class="col">
                <div class="stat-card locked">
                    <div class="value"><?= $stats['locked'] ?></div>
                    <div class="label">Locked</div>
                </div>
            </div>
            <div class="col">
                <div class="stat-card offline">
                    <div class="value"><?= $stats['offline'] ?></div>
                    <div class="label">Offline</div>
                </div>
            </div>
            <div class="col">
                <div class="stat-card">
                    <div class="value"><?= $stats['active_sessions'] ?></div>
                    <div class="label">Active Sessions</div>
                </div>
            </div>
        </div>

        <?php if ($showAutoDeployUi): ?>
        <div class="xp-card mb-3" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <h6 class="mb-1"><i class="bi bi-rocket-takeoff me-2"></i>Auto-Deployment Policy</h6>
                        <div class="small text-muted">
                            Enabled: <strong><?= !empty($autoDeployConfig['enabled']) ? 'Yes' : 'No' ?></strong> |
                            Default deny unknown: <strong><?= !empty($autoDeployConfig['default_deny_unknown_networks']) ? 'Yes' : 'No' ?></strong> |
                            Max bulk jobs: <strong><?= (int) ($autoDeployConfig['max_bulk_jobs_per_request'] ?? 25) ?></strong>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="badge text-bg-secondary">Pending <?= (int) ($deploySummary['pending'] ?? 0) ?></span>
                        <span class="badge text-bg-info">In Progress <?= (int) ($deploySummary['in_progress'] ?? 0) ?></span>
                        <span class="badge text-bg-success">Installed <?= (int) ($deploySummary['installed'] ?? 0) ?></span>
                        <span class="badge text-bg-danger">Failed <?= (int) ($deploySummary['failed'] ?? 0) ?></span>
                        <span class="badge text-bg-dark">Excluded <?= (int) ($deploySummary['excluded'] ?? 0) ?></span>
                    </div>
                </div>
                <div class="mt-2 d-flex gap-2 flex-wrap">
                    <?php if ($isAdmin): ?>
                    <button class="btn btn-sm btn-outline-info" onclick="evaluateDeployPolicy()">Evaluate Policy</button>
                    <button class="btn btn-sm btn-primary" onclick="queueEligibleDeployments()">Queue Client Updates</button>
                    <button class="btn btn-sm btn-success" onclick="runDeployJobs()">Run Queued Updates</button>
                    <button class="btn btn-sm btn-warning" onclick="oneButtonUpdateAll()">One-Button Update All</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($showKioskUi && $kioskDeviceService): ?>
        <!-- Kiosk phones (mobile browser QR) -->
        <div class="xp-card mb-4" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden;">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: transparent; border-bottom: 1px solid var(--border); padding: 1rem 1.5rem;">
                <h5 class="mb-0"><i class="bi bi-phone me-2"></i>Kiosk phones</h5>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-sm btn-outline-light" href="<?= e(rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') . '/kiosk_mobile.php') ?>" target="_blank" rel="noopener">Open mobile kiosk</a>
                    <?php if ($kioskDeviceService->tableExists()): ?>
                    <button type="button" class="btn btn-sm btn-primary" onclick="openAddKioskModal()">Add device</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (!$kioskDeviceService->tableExists()): ?>
                    <div class="alert alert-warning m-3 mb-0">The <code>kiosk_devices</code> table is missing. Run <code>php database/migrate.php</code> to enable mobile kiosk pairing.</div>
                <?php else: ?>
                    <p class="text-muted small px-3 pt-3 mb-2">Register each phone by MAC (from Wi‑Fi details in Settings). Runtime auth uses a token or short pairing code—the browser cannot read the device MAC.</p>
                    <div class="table-responsive px-3 pb-3">
                        <table class="table table-sm table-dark align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Label</th>
                                    <th>MAC</th>
                                    <th>Floor</th>
                                    <th>Token</th>
                                    <th>Last used</th>
                                    <th>Status</th>
                                    <th style="min-width: 220px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kioskDevices as $kd): ?>
                                    <?php $hasTok = !empty($kd['token_hash']); ?>
                                    <tr>
                                        <td><?= e($kd['label']) ?></td>
                                        <td><code><?= e($kd['mac_address']) ?></code></td>
                                        <td><?= e($kd['floor_name'] ?? '') ?></td>
                                        <td><?= $hasTok ? '<span class="badge text-bg-success">Set</span>' : '<span class="badge text-bg-secondary">None</span>' ?></td>
                                        <td class="small"><?= !empty($kd['last_used_at']) ? e($kd['last_used_at']) : '—' ?></td>
                                        <td><?= !empty($kd['is_active']) ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Off</span>' ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-light py-0" onclick="rotateKioskToken(<?= (int) $kd['id'] ?>)">Rotate token</button>
                                            <button type="button" class="btn btn-sm btn-outline-info py-0" onclick="createKioskPairing(<?= (int) $kd['id'] ?>)">Pairing QR</button>
                                            <button type="button" class="btn btn-sm btn-outline-warning py-0" onclick="toggleKioskActive(<?= (int) $kd['id'] ?>, <?= !empty($kd['is_active']) ? 'false' : 'true' ?>)"><?= !empty($kd['is_active']) ? 'Deactivate' : 'Activate' ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($kioskDevices)): ?>
                                    <tr><td colspan="7" class="text-muted">No kiosk phones yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Active Sessions -->
        <?php if (!empty($activeSessions)): ?>
        <div class="xp-card mb-4" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden;">
            <div class="card-header" style="background: transparent; border-bottom: 1px solid var(--border); padding: 1rem 1.5rem;">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Active Sessions</h5>
            </div>
            <div class="card-body p-0">
                <table class="session-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>LRN</th>
                            <th>PC</th>
                            <th>Check-in Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeSessions as $session): ?>
                        <tr>
                            <td><?= e($session['first_name'] . ' ' . $session['last_name']) ?></td>
                            <td><code><?= e($session['lrn']) ?></code></td>
                            <td><?= e($session['hostname']) ?></td>
                            <td><?= date('g:i A', strtotime($session['checkin_time'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="forceLogout(<?= $session['user_id'] ?>, '<?= e($session['lrn']) ?>')">
                                    <i class="bi bi-box-arrow-right"></i> Force Logout
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- PC Grid -->
        <h5 class="mb-3"><i class="bi bi-pc-display me-2"></i>Lab Computers</h5>
        <?php if (!empty($unassignedPcs)): ?>
        <div class="alert alert-warning py-2">
            <strong>Unassigned PCs:</strong> <?= count($unassignedPcs) ?> discovered device(s) are waiting for floor/station assignment.
        </div>
        <div class="xp-card mb-3" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden;">
            <div class="card-header" style="background: transparent; border-bottom: 1px solid var(--border); padding: 0.75rem 1rem;">
                <strong>Discovered PCs (Unassigned)</strong>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-dark align-middle mb-0">
                        <thead><tr><th>Hostname</th><th>IP</th><th>MAC</th><th>Assign</th></tr></thead>
                        <tbody>
                        <?php foreach ($unassignedPcs as $upc): ?>
                            <tr>
                                <td><?= e($upc['hostname'] ?? '') ?></td>
                                <td><?= e($upc['ip_address'] ?? '') ?></td>
                                <td><?= e($upc['mac_address'] ?? '') ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <select class="form-select form-select-sm" id="station-select-<?= (int) $upc['id'] ?>" style="max-width: 180px;">
                                            <option value="">No station</option>
                                            <?php foreach ($stationsForFloor as $st): ?>
                                                <option value="<?= (int) $st['id'] ?>"><?= e($st['station_code']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm btn-primary" onclick="assignPc(<?= (int) $upc['id'] ?>)">Assign to this floor</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="pc-grid" id="pc-grid">
            <?php foreach ($pcs as $pc): 
                $status = strtolower((string) ($pc['effective_status'] ?? $pc['status'] ?? 'offline'));
                $session = $db->fetch(
                    "SELECT u.first_name, u.last_name FROM pc_sessions ps JOIN users u ON ps.user_id = u.id WHERE ps.pc_id = ? AND ps.status = 'active'",
                    [$pc['id']]
                );
            ?>
            <div class="pc-card <?= $status ?>" id="pc-<?= $pc['id'] ?>">
                <div class="pc-header">
                    <span class="pc-name"><i class="bi bi-pc-display me-1"></i><?= e($pc['hostname']) ?></span>
                    <span class="pc-status <?= $status ?>"><?= $status ?></span>
                </div>
                <?php if ($showAutoDeployUi): ?>
                <div class="mb-1">
                    <span class="badge text-bg-secondary">
                        Deploy: <?= e((string) ($pc['deployment_status'] ?? 'n/a')) ?>
                    </span>
                    <?php if (!empty($pc['deployment_tag'])): ?>
                        <span class="badge text-bg-dark"><?= e($pc['deployment_tag']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($session): ?>
                <div class="pc-user">
                    <i class="bi bi-person me-1"></i><?= e($session['first_name'] . ' ' . $session['last_name']) ?>
                </div>
                <?php else: ?>
                <div class="pc-user text-muted">No active user</div>
                <?php endif; ?>
                <div class="pc-actions">
                    <?php if ($status === 'locked'): ?>
                    <button class="unlock-btn" onclick="unlockPC(<?= $pc['id'] ?>)">
                        <i class="bi bi-unlock"></i> Unlock
                    </button>
                    <?php else: ?>
                    <button class="lock-btn" onclick="lockPC(<?= $pc['id'] ?>)">
                        <i class="bi bi-lock"></i> Lock
                    </button>
                    <?php endif; ?>
                    <button onclick="sendMessage(<?= $pc['id'] ?>)">
                        <i class="bi bi-chat-text"></i> Message
                    </button>
                    <button onclick="openPcThreadsModal(<?= (int) $pc['id'] ?>)">
                        <i class="bi bi-chat-dots"></i> Chats
                    </button>
                    <button class="restart-btn" onclick="restartPC(<?= $pc['id'] ?>)">
                        <i class="bi bi-arrow-clockwise"></i> Restart
                    </button>
                    <button onclick="shutdownPC(<?= $pc['id'] ?>)">
                        <i class="bi bi-power"></i> Shutdown
                    </button>
                    <button onclick="unassignPc(<?= $pc['id'] ?>)">
                        <i class="bi bi-box-arrow-up-left"></i> Unassign
                    </button>
                </div>
                <?php if ($isAdmin && $showAutoDeployUi): ?>
                <div class="pc-actions">
                    <button onclick="queueDeployForPc(<?= (int) $pc['id'] ?>)">
                        <i class="bi bi-upload"></i> Update
                    </button>
                    <button onclick="retryDeployForPc(<?= (int) $pc['id'] ?>)">
                        <i class="bi bi-arrow-repeat"></i> Retry
                    </button>
                    <?php if ((int) ($pc['auto_deploy_enabled'] ?? 1) === 1): ?>
                    <button onclick="setDeployPolicy(<?= (int) $pc['id'] ?>, false, 'exclude_auto_deploy')">
                        <i class="bi bi-slash-circle"></i> Exclude
                    </button>
                    <?php else: ?>
                    <button onclick="setDeployPolicy(<?= (int) $pc['id'] ?>, true, '')">
                        <i class="bi bi-check2-circle"></i> Include
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (empty($pcs)): ?>
            <div class="col-12 text-center text-muted py-5">
                <p>No lab PCs registered yet. PCs will appear here when they boot and register via the startup script.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Message Modal -->
    <div class="modal fade" id="messageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--bg-card); border: 1px solid var(--border);">
                <div class="modal-header" style="border-color: var(--border);">
                    <h5 class="modal-title">Send Message to PC</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="messagePCId">
                    <p class="text-muted small">Shows on the student PC via the agent. Starts a chat thread so replies can be tracked.</p>
                    <textarea class="form-control" id="messageText" rows="3" placeholder="Enter message..." style="background: var(--bg-dark); border-color: var(--border); color: var(--text);"></textarea>
                </div>
                <div class="modal-footer" style="border-color: var(--border);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="sendPCMessage()">Send</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PC chat threads modal -->
    <div class="modal fade" id="threadsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content" style="background: var(--bg-card); border: 1px solid var(--border);">
                <div class="modal-header" style="border-color: var(--border);">
                    <h5 class="modal-title">PC chat threads</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="threadsPcId">
                    <div id="threadsList" class="list-group mb-3 small"></div>
                    <h6 class="text-muted">Messages</h6>
                    <div id="threadMessages" class="border rounded p-2" style="min-height: 180px; max-height: 320px; overflow-y: auto; background: var(--bg-dark); border-color: var(--border) !important; font-size: 0.9rem;"></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($showKioskUi): ?>
    <!-- Kiosk: add device -->
    <div class="modal fade" id="addKioskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--bg-card); border: 1px solid var(--border); color: var(--text);">
                <div class="modal-header" style="border-color: var(--border);">
                    <h5 class="modal-title">Add kiosk phone</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small">Label</label>
                        <input type="text" class="form-control form-control-sm" id="kioskAddLabel" placeholder="e.g. Front desk phone" style="background: var(--bg-dark); border-color: var(--border); color: var(--text);">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">MAC address (12 hex, from phone settings)</label>
                        <input type="text" class="form-control form-control-sm" id="kioskAddMac" placeholder="aa:bb:cc:dd:ee:ff" style="background: var(--bg-dark); border-color: var(--border); color: var(--text);">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small">Default floor</label>
                        <select class="form-select form-select-sm" id="kioskAddFloor" style="background: var(--bg-dark); border-color: var(--border); color: var(--text);">
                            <?php foreach ($floors as $fl): ?>
                                <option value="<?= (int) $fl['id'] ?>" <?= ((int) $fl['id'] === $selectedFloor) ? 'selected' : '' ?>><?= e($fl['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" style="border-color: var(--border);">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="submitAddKiosk()">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Kiosk: show API token once -->
    <div class="modal fade" id="kioskTokenModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--bg-card); border: 1px solid var(--border); color: var(--text);">
                <div class="modal-header" style="border-color: var(--border);">
                    <h5 class="modal-title">Kiosk API token</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-warning mb-2">Copy this now — it will not be shown again.</p>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control font-monospace small" id="kioskTokenValue" readonly style="background: var(--bg-dark); border-color: var(--border); color: var(--text);">
                        <button class="btn btn-outline-secondary" type="button" onclick="copyKioskToken()">Copy</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kiosk: pairing QR -->
    <div class="modal fade" id="kioskPairModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--bg-card); border: 1px solid var(--border); color: var(--text);">
                <div class="modal-header" style="border-color: var(--border);">
                    <h5 class="modal-title">Pair phone</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="small text-muted mb-2">Scan on the phone or enter the code on the mobile kiosk page (10 min).</p>
                    <div id="kioskPairQr" class="d-inline-block p-2 bg-white rounded mb-2"></div>
                    <div class="font-monospace fs-5 mb-1" id="kioskPairCode"></div>
                    <div class="small text-muted" id="kioskPairExpiry"></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($showKioskUi): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSQX0FslNhTDadL4O5SAGapGt4FodqL8My0mA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const csrfToken = <?= json_encode(\csrf_token()) ?>;
    const teacherId = <?= json_encode(\XPLabs\Lib\Auth::id()) ?>;
    const selectedFloorId = <?= (int) $selectedFloor ?>;
    const appBase = <?= json_encode(rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/xplabs/dashboard_lab_pcs.php'), '/')) ?>;
    const apiUrl = (path) => `${appBase}${path}`;
    const apiPhpUrl = (path) => `${appBase}${path}.php`;

    async function lockPC(pcId) {
        try {
            const res = await fetch(apiPhpUrl('/api/lab/queue-command'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ pc_id: pcId, command_type: 'lock', issued_by: teacherId })
            });
            const data = await res.json();
            if (data.success) {
                showToast('Lock command sent', 'success');
                setTimeout(refreshPCs, 2000);
            } else {
                showToast(data.error || ('Lock failed (HTTP ' + res.status + ')'), 'error');
            }
        } catch (e) {
            showToast('Failed to send command', 'error');
        }
    }

    async function unlockPC(pcId) {
        try {
            const res = await fetch(apiPhpUrl('/api/lab/queue-command'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ pc_id: pcId, command_type: 'unlock', issued_by: teacherId })
            });
            const data = await res.json();
            if (data.success) {
                showToast('Unlock command sent', 'success');
                setTimeout(refreshPCs, 2000);
            } else {
                showToast(data.error || ('Unlock failed (HTTP ' + res.status + ')'), 'error');
            }
        } catch (e) {
            showToast('Failed to send command', 'error');
        }
    }

    async function restartPC(pcId) {
        if (!confirm('Restart this PC now? Unsaved work will be lost.')) return;
        try {
            const res = await fetch(apiPhpUrl('/api/lab/queue-command'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ pc_id: pcId, command_type: 'restart', issued_by: teacherId })
            });
            const data = await res.json();
            if (data.success) {
                showToast('Restart command sent', 'success');
                setTimeout(refreshPCs, 2000);
            } else {
                showToast(data.error || 'Failed to send command', 'error');
            }
        } catch (e) {
            showToast('Failed to send command', 'error');
        }
    }

    async function shutdownPC(pcId) {
        if (!confirm('Shutdown this PC now?')) return;
        try {
            const res = await fetch(apiPhpUrl('/api/lab/queue-command'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ pc_id: pcId, command_type: 'shutdown', issued_by: teacherId })
            });
            const data = await res.json();
            if (data.success) {
                showToast('Shutdown command sent', 'success');
                setTimeout(refreshPCs, 2000);
            } else {
                showToast(data.error || 'Failed to send command', 'error');
            }
        } catch (e) {
            showToast('Failed to send command', 'error');
        }
    }

    function sendMessage(pcId) {
        document.getElementById('messagePCId').value = pcId;
        new bootstrap.Modal(document.getElementById('messageModal')).show();
    }

    async function sendPCMessage() {
        const pcId = document.getElementById('messagePCId').value;
        const message = document.getElementById('messageText').value;
        if (!message) return;

        try {
            const res = await fetch(apiPhpUrl('/api/lab/pc-message'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ pc_id: Number(pcId), message })
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                showToast('Message sent (thread #' + (data.thread_id || '') + ')', 'success');
                bootstrap.Modal.getInstance(document.getElementById('messageModal')).hide();
                document.getElementById('messageText').value = '';
            } else {
                const err = data.error || data.message || ('HTTP ' + res.status);
                showToast('Failed to send: ' + err, 'error');
            }
        } catch (e) {
            showToast('Failed to send message: ' + (e.message || 'network error'), 'error');
        }
    }

    let threadsPollTimer = null;
    let threadsSelectedId = null;

    function openPcThreadsModal(pcId) {
        document.getElementById('threadsPcId').value = pcId;
        threadsSelectedId = null;
        document.getElementById('threadMessages').innerHTML = '<span class="text-muted">Select a thread</span>';
        new bootstrap.Modal(document.getElementById('threadsModal')).show();
        loadThreadsList(pcId);
        if (threadsPollTimer) clearInterval(threadsPollTimer);
        threadsPollTimer = setInterval(() => {
            if (!document.getElementById('threadsModal').classList.contains('show')) return;
            loadThreadsList(pcId, true);
            if (threadsSelectedId) loadThreadMessages(threadsSelectedId, true);
        }, 5000);
    }

    document.getElementById('threadsModal')?.addEventListener('hidden.bs.modal', () => {
        if (threadsPollTimer) {
            clearInterval(threadsPollTimer);
            threadsPollTimer = null;
        }
    });

    async function loadThreadsList(pcId, silent) {
        try {
            const res = await fetch(apiPhpUrl('/api/lab/pc-messages') + '?pc_id=' + encodeURIComponent(pcId));
            const data = await res.json();
            const el = document.getElementById('threadsList');
            if (!data.success || !data.threads || data.threads.length === 0) {
                el.innerHTML = '<div class="text-muted">No threads yet. Send a message to start.</div>';
                return;
            }
            el.innerHTML = data.threads.map(t => `
                <button type="button" class="list-group-item list-group-item-action ${threadsSelectedId === t.id ? 'active' : ''}"
                  onclick="selectThread(${t.id})">
                  #${t.id} · ${t.first_name} ${t.last_name} · ${t.started_at || ''}
                </button>`).join('');
        } catch (e) {
            if (!silent) showToast('Could not load threads', 'error');
        }
    }

    async function selectThread(threadId) {
        threadsSelectedId = threadId;
        const pcId = document.getElementById('threadsPcId').value;
        await loadThreadsList(pcId, true);
        await loadThreadMessages(threadId);
    }

    async function loadThreadMessages(threadId, silent) {
        try {
            const res = await fetch(apiPhpUrl('/api/lab/pc-messages') + '?thread_id=' + encodeURIComponent(threadId));
            const data = await res.json();
            const box = document.getElementById('threadMessages');
            if (!data.success || !data.messages) {
                box.innerHTML = '<span class="text-muted">No messages</span>';
                return;
            }
            box.innerHTML = data.messages.map(m => {
                const who = m.sender_role === 'student' ? 'Student' : 'Instructor';
                const name = (m.first_name || '') + ' ' + (m.last_name || '');
                return `<div class="mb-2"><span class="badge bg-secondary">${who}</span> ${name.trim()} <small class="text-muted">${m.created_at || ''}</small><div>${escapeHtml(m.body || '')}</div></div>`;
            }).join('');
        } catch (e) {
            if (!silent) showToast('Could not load messages', 'error');
        }
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    async function forceLogout(userId, lrn) {
        if (!confirm('Force logout this student?')) return;
        try {
            const res = await fetch(apiPhpUrl('/api/session/force-logout'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ user_id: userId, lrn })
            });
            const data = await res.json();
            if (data.success) {
                showToast('Student logged out', 'success');
                setTimeout(refreshPCs, 2000);
            }
        } catch (e) {
            showToast('Failed to logout student', 'error');
        }
    }

    async function lockAllPCs() {
        if (!confirm('Lock all lab PCs?')) return;
        try {
            const res = await fetch(apiPhpUrl('/api/lab/queue-command'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ pc_id: 'all', command_type: 'lock', issued_by: teacherId })
            });
            const data = await res.json();
            if (data.success) {
                showToast('Lock all command sent', 'success');
                setTimeout(refreshPCs, 3000);
            }
        } catch (e) {
            showToast('Failed to send command', 'error');
        }
    }

    async function unlockAllPCs() {
        try {
            const res = await fetch(apiPhpUrl('/api/lab/queue-command'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ pc_id: 'all', command_type: 'unlock', issued_by: teacherId })
            });
            const data = await res.json();
            if (data.success) {
                showToast('Unlock all command sent', 'success');
                setTimeout(refreshPCs, 3000);
            }
        } catch (e) {
            showToast('Failed to send command', 'error');
        }
    }

    async function refreshPCs() {
        // Do not wipe modals/forms while admin is typing (message, assign, threads, etc.)
        if (document.querySelector('.modal.show')) {
            return;
        }
        location.reload();
    }

    async function discoverPCs() {
        try {
            const res = await fetch(apiPhpUrl('/api/pc/discover'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({})
            });
            const data = await res.json();
            if (data.success) {
                showToast(`Discovery done: ${data.discovered} scanned, ${data.created} new, ${data.updated} updated`, 'success');
                setTimeout(refreshPCs, 1200);
                return;
            }
            showToast(data.error || 'Discovery failed', 'error');
        } catch (e) {
            showToast('Discovery failed', 'error');
        }
    }

    async function assignPc(pcId) {
        const stationEl = document.getElementById(`station-select-${pcId}`);
        const stationId = stationEl ? stationEl.value : '';
        try {
            const res = await fetch(apiPhpUrl('/api/pc/assign'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({
                    pc_id: pcId,
                    floor_id: <?= (int) $selectedFloor ?>,
                    station_id: stationId ? parseInt(stationId, 10) : null
                })
            });
            const data = await res.json();
            if (data.success) {
                showToast('PC assigned', 'success');
                setTimeout(refreshPCs, 800);
                return;
            }
            showToast(data.error || 'Assign failed', 'error');
        } catch (e) {
            showToast('Assign failed', 'error');
        }
    }

    async function unassignPc(pcId) {
        try {
            const res = await fetch(apiPhpUrl('/api/pc/unassign'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ pc_id: pcId })
            });
            const data = await res.json();
            if (data.success) {
                showToast('PC moved to unassigned', 'success');
                setTimeout(refreshPCs, 800);
                return;
            }
            showToast(data.error || 'Unassign failed', 'error');
        } catch (e) {
            showToast('Unassign failed', 'error');
        }
    }

    async function evaluateDeployPolicy() {
        try {
            const res = await fetch(apiPhpUrl('/api/pc/deploy-evaluate') + '?eligible_only=0');
            const data = await res.json();
            if (data.success) {
                showToast(`Policy evaluated for ${data.count} PC(s)`, 'success');
                setTimeout(refreshPCs, 900);
                return;
            }
            showToast(data.error || 'Policy evaluation failed', 'error');
        } catch (e) {
            showToast('Policy evaluation failed', 'error');
        }
    }

    async function queueEligibleDeployments() {
        const pcIds = Array.from(document.querySelectorAll('[id^="pc-"]'))
            .map(el => parseInt(el.id.replace('pc-', ''), 10))
            .filter(n => Number.isFinite(n) && n > 0);
        if (!pcIds.length) {
            showToast('No PCs found to queue', 'error');
            return;
        }
        try {
            const res = await fetch(apiPhpUrl('/api/pc/update-queue'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ pc_ids: pcIds, trigger_type: 'manual' })
            });
            const data = await res.json();
            if (data.success) {
                showToast(`Queued ${data.count_queued} update job(s)`, 'success');
                return;
            }
            showToast(data.error || 'Queue failed', 'error');
        } catch (e) {
            showToast('Queue failed', 'error');
        }
    }

    async function queueDeployForPc(pcId) {
        try {
            const res = await fetch(apiPhpUrl('/api/pc/update-queue'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ pc_ids: [pcId], trigger_type: 'manual' })
            });
            const data = await res.json();
            if (data.success) {
                showToast(`PC ${pcId} queued for update`, 'success');
                return;
            }
            showToast(data.error || 'Queue failed', 'error');
        } catch (e) {
            showToast('Queue failed', 'error');
        }
    }

    async function retryDeployForPc(pcId) {
        try {
            const res = await fetch(apiPhpUrl('/api/pc/update-retry'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ pc_id: pcId })
            });
            const data = await res.json();
            if (data.success) {
                showToast(`Update retry queued for PC ${pcId}`, 'success');
                return;
            }
            showToast(data.error || 'Retry failed', 'error');
        } catch (e) {
            showToast('Retry failed', 'error');
        }
    }

    async function runDeployJobs() {
        try {
            const res = await fetch(apiPhpUrl('/api/pc/update-run'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ limit: 3 })
            });
            const data = await res.json();
            if (data.success) {
                showToast(`Processed ${data.processed} update job(s)`, 'success');
                setTimeout(refreshPCs, 1200);
                return;
            }
            showToast(data.error || 'Run failed', 'error');
        } catch (e) {
            showToast('Run failed', 'error');
        }
    }

    async function oneButtonUpdateAll() {
        try {
            await queueEligibleDeployments();
            await runDeployJobs();
        } catch (e) {
            showToast('One-button update failed', 'error');
        }
    }

    function openAddKioskModal() {
        document.getElementById('kioskAddLabel').value = '';
        document.getElementById('kioskAddMac').value = '';
        new bootstrap.Modal(document.getElementById('addKioskModal')).show();
    }

    async function submitAddKiosk() {
        const label = document.getElementById('kioskAddLabel').value.trim();
        const mac = document.getElementById('kioskAddMac').value.trim();
        const floorId = parseInt(document.getElementById('kioskAddFloor').value, 10);
        try {
            const res = await fetch(apiPhpUrl('/api/kiosk/devices'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ label, mac_address: mac, floor_id: floorId })
            });
            const data = await res.json();
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('addKioskModal'))?.hide();
                showToast('Kiosk device added', 'success');
                location.reload();
                return;
            }
            showToast(data.error || 'Failed', 'error');
        } catch (e) {
            showToast('Request failed', 'error');
        }
    }

    async function rotateKioskToken(id) {
        if (!confirm('Generate a new token? The old token will stop working.')) return;
        try {
            const res = await fetch(apiPhpUrl('/api/kiosk/devices'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ action: 'rotate_token', id })
            });
            const data = await res.json();
            if (data.success && data.token) {
                document.getElementById('kioskTokenValue').value = data.token;
                new bootstrap.Modal(document.getElementById('kioskTokenModal')).show();
                return;
            }
            showToast(data.error || 'Rotate failed', 'error');
        } catch (e) {
            showToast('Rotate failed', 'error');
        }
    }

    function copyKioskToken() {
        const el = document.getElementById('kioskTokenValue');
        el.select();
        navigator.clipboard.writeText(el.value).then(() => showToast('Copied', 'success')).catch(() => {});
    }

    async function createKioskPairing(id) {
        try {
            const res = await fetch(apiPhpUrl('/api/kiosk/devices'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ action: 'pairing_code', id })
            });
            const data = await res.json();
            if (!data.success) {
                showToast(data.error || 'Failed', 'error');
                return;
            }
            const qrEl = document.getElementById('kioskPairQr');
            qrEl.innerHTML = '';
            const pairUrl = data.pair_url || '';
            if (typeof QRCode !== 'undefined' && pairUrl) {
                new QRCode(qrEl, { text: pairUrl, width: 200, height: 200 });
            }
            document.getElementById('kioskPairCode').textContent = data.pairing_code || '';
            document.getElementById('kioskPairExpiry').textContent = data.expires_at ? ('Expires: ' + data.expires_at) : '';
            new bootstrap.Modal(document.getElementById('kioskPairModal')).show();
        } catch (e) {
            showToast('Pairing failed', 'error');
        }
    }

    async function toggleKioskActive(id, active) {
        try {
            const res = await fetch(apiPhpUrl('/api/kiosk/devices'), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ id, is_active: !!active })
            });
            const data = await res.json();
            if (data.success) {
                showToast('Updated', 'success');
                location.reload();
                return;
            }
            showToast(data.error || 'Update failed', 'error');
        } catch (e) {
            showToast('Update failed', 'error');
        }
    }

    async function setDeployPolicy(pcId, enabled, tag) {
        try {
            const res = await fetch(apiPhpUrl('/api/pc/deploy-policy'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({
                    pc_id: pcId,
                    auto_deploy_enabled: !!enabled,
                    deployment_tag: tag || ''
                })
            });
            const data = await res.json();
            if (data.success) {
                showToast(`Deployment policy updated for PC ${pcId}`, 'success');
                setTimeout(refreshPCs, 800);
                return;
            }
            showToast(data.error || 'Policy update failed', 'error');
        } catch (e) {
            showToast('Policy update failed', 'error');
        }
    }

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    // Auto-refresh every 30 seconds
    setInterval(refreshPCs, 30000);
    </script>
</body>
</html>