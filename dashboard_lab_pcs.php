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

$db = Database::getInstance();
$pcService = new PCService();
$labService = new LabService();

$floors = $labService->getFloors();
$selectedFloor = (int) ($_GET['floor_id'] ?? ($floors[0]['id'] ?? 0));
$stats = $pcService->getStats($selectedFloor);
$activeSessions = $pcService->getActiveSessions($selectedFloor);
$pcs = $db->fetchAll(
    "SELECT lp.*, lf.name as floor_name
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
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .pc-card {
            background: var(--bg-card);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 1rem;
            transition: all 0.3s;
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
            display: flex;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }
        .pc-actions button {
            flex: 1;
            padding: 0.35rem;
            font-size: 0.7rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg-dark);
            color: var(--text);
            cursor: pointer;
            transition: all 0.2s;
        }
        .pc-actions button:hover {
            border-color: var(--blue);
            background: rgba(59, 130, 246, 0.1);
        }
        .pc-actions button.lock-btn:hover { border-color: var(--red); background: rgba(239, 68, 68, 0.1); }
        .pc-actions button.unlock-btn:hover { border-color: var(--green); background: rgba(34, 197, 94, 0.1); }
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
            <a href="dashboard_teacher.php" class="text-decoration-none" style="color: var(--text);">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <h5 class="mb-0" style="color: var(--text);">🖥️ Lab PC Management</h5>
        </div>
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-light" onclick="refreshPCs()">
                <i class="bi bi-arrow-clockwise"></i> Refresh
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
        <div class="pc-grid" id="pc-grid">
            <?php foreach ($pcs as $pc): 
                $status = $pc['status'];
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
                </div>
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
                    <textarea class="form-control" id="messageText" rows="3" placeholder="Enter message..." style="background: var(--bg-dark); border-color: var(--border); color: var(--text);"></textarea>
                </div>
                <div class="modal-footer" style="border-color: var(--border);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="sendPCMessage()">Send</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const csrfToken = <?= json_encode(\csrf_token()) ?>;
    const teacherId = <?= json_encode(\XPLabs\Lib\Auth::id()) ?>;

    async function lockPC(pcId) {
        try {
            const res = await fetch('/api/lab/queue-command', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ pc_id: pcId, command_type: 'lock', issued_by: teacherId })
            });
            const data = await res.json();
            if (data.success) {
                showToast('Lock command sent', 'success');
                setTimeout(refreshPCs, 2000);
            }
        } catch (e) {
            showToast('Failed to send command', 'error');
        }
    }

    async function unlockPC(pcId) {
        try {
            const res = await fetch('/api/lab/queue-command', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ pc_id: pcId, command_type: 'unlock', issued_by: teacherId })
            });
            const data = await res.json();
            if (data.success) {
                showToast('Unlock command sent', 'success');
                setTimeout(refreshPCs, 2000);
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
            const res = await fetch('/api/lab/queue-command', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ pc_id: pcId, command_type: 'message', issued_by: teacherId, params: { message } })
            });
            const data = await res.json();
            if (data.success) {
                showToast('Message sent', 'success');
                bootstrap.Modal.getInstance(document.getElementById('messageModal')).hide();
                document.getElementById('messageText').value = '';
            }
        } catch (e) {
            showToast('Failed to send message', 'error');
        }
    }

    async function forceLogout(userId, lrn) {
        if (!confirm('Force logout this student?')) return;
        try {
            const res = await fetch('/api/session/force-logout', {
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
            const res = await fetch('/api/lab/queue-command', {
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
            const res = await fetch('/api/lab/queue-command', {
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
        location.reload();
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