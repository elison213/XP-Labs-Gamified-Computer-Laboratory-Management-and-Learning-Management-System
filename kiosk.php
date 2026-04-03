<?php
/**
 * XPLabs - Kiosk QR Scanner Page
 * Full-screen kiosk mode for computer lab check-in/check-out.
 * Looks like a computer cafe management system.
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Services\LabService;
use XPLabs\Services\AttendanceService;

// Kiosk doesn't require auth - it's a public scanning station
$db = Database::getInstance();
$labService = new LabService();
$attendanceService = new AttendanceService();

$floors = $labService->getFloors();
$selectedFloor = (int) ($_GET['floor_id'] ?? ($floors[0]['id'] ?? 0));
$layout = $selectedFloor ? $labService->getFloorLayout($selectedFloor) : [];
$stations = $layout['stations'] ?? [];
$stats = $labService->getStats($selectedFloor);

// Get active sessions
$activeSessions = $db->fetchAll(
    "SELECT s.*, c.name as course_name
     FROM attendance_sessions s
     JOIN courses c ON s.course_id = c.id
     WHERE s.status = 'active'
     ORDER BY s.created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XPLabs Kiosk - Lab Scanner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --xp-green: #10b981;
            --xp-yellow: #f59e0b;
            --xp-red: #ef4444;
            --xp-gray: #64748b;
            --xp-dark: #0f172a;
            --xp-card: #1e293b;
            --xp-border: #334155;
        }

        * { box-sizing: border-box; }

        body {
            background: var(--xp-dark);
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Header */
        .kiosk-header {
            background: var(--xp-card);
            border-bottom: 1px solid var(--xp-border);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .kiosk-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .kiosk-brand h1 {
            font-size: 1.25rem;
            font-weight: 800;
            margin: 0;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .kiosk-time {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--xp-green);
        }

        .kiosk-date {
            font-size: 0.75rem;
            color: var(--xp-gray);
        }

        /* Main Layout */
        .kiosk-main {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 1rem;
            padding: 1rem;
            min-height: calc(100vh - 60px);
        }

        /* Scanner Panel */
        .scanner-panel {
            background: var(--xp-card);
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid var(--xp-border);
        }

        .scanner-title {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--xp-gray);
            margin-bottom: 1rem;
        }

        .scanner-input {
            background: var(--xp-dark);
            border: 2px solid var(--xp-border);
            border-radius: 0.5rem;
            padding: 1rem;
            color: #fff;
            font-size: 1.1rem;
            font-family: 'JetBrains Mono', monospace;
            width: 100%;
            text-align: center;
            transition: border-color 0.2s;
        }

        .scanner-input:focus {
            outline: none;
            border-color: var(--xp-green);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }

        .scanner-input.checkout-mode {
            border-color: var(--xp-yellow);
        }

        .scanner-input.checkout-mode:focus {
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
        }

        .mode-toggle {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .mode-btn {
            flex: 1;
            padding: 0.5rem;
            border: 2px solid var(--xp-border);
            border-radius: 0.5rem;
            background: transparent;
            color: var(--xp-gray);
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .mode-btn.active {
            border-color: var(--xp-green);
            background: rgba(16, 185, 129, 0.1);
            color: var(--xp-green);
        }

        .mode-btn.active.checkout {
            border-color: var(--xp-yellow);
            background: rgba(245, 158, 11, 0.1);
            color: var(--xp-yellow);
        }

        /* Scan Result */
        .scan-result {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
            display: none;
        }

        .scan-result.success {
            display: block;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--xp-green);
        }

        .scan-result.error {
            display: block;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--xp-red);
        }

        .scan-result .name {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .scan-result .message {
            font-size: 0.8rem;
            color: var(--xp-gray);
        }

        .scan-result .points {
            font-size: 0.85rem;
            color: var(--xp-green);
            font-weight: 600;
        }

        /* Floor Plan Panel */
        .floor-panel {
            background: var(--xp-card);
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid var(--xp-border);
            display: flex;
            flex-direction: column;
        }

        .floor-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .floor-title {
            font-size: 1rem;
            font-weight: 700;
        }

        .floor-stats {
            display: flex;
            gap: 1rem;
        }

        .stat-badge {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .stat-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .stat-dot.active { background: var(--xp-green); box-shadow: 0 0 6px rgba(16, 185, 129, 0.5); }
        .stat-dot.idle { background: var(--xp-yellow); }
        .stat-dot.offline { background: var(--xp-gray); }
        .stat-dot.maintenance { background: var(--xp-red); }

        /* Board */
        .lab-board {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: linear-gradient(135deg, #334155, #475569);
            color: #f8fafc;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            text-align: center;
            margin-bottom: 1rem;
        }

        /* Seat Grid */
        .seat-grid {
            display: grid;
            gap: 0.5rem;
            grid-template-columns: repeat(6, 1fr);
            flex: 1;
        }

        .seat {
            background: var(--xp-dark);
            border: 2px solid var(--xp-border);
            border-radius: 0.5rem;
            padding: 0.5rem;
            text-align: center;
            min-height: 100px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .seat.active {
            border-color: var(--xp-green);
            background: linear-gradient(180deg, rgba(16, 185, 129, 0.1), var(--xp-dark));
        }

        .seat.idle {
            border-color: var(--xp-yellow);
            background: linear-gradient(180deg, rgba(245, 158, 11, 0.1), var(--xp-dark));
        }

        .seat.offline {
            border-color: var(--xp-border);
            opacity: 0.5;
        }

        .seat.maintenance {
            border-color: var(--xp-red);
            background: linear-gradient(180deg, rgba(239, 68, 68, 0.1), var(--xp-dark));
        }

        .seat.just-scanned {
            animation: scanPulse 1s ease-out;
        }

        @keyframes scanPulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 0 20px 10px rgba(16, 185, 129, 0.2); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .seat-code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--xp-gray);
        }

        .seat-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.7rem;
            color: #fff;
            background: var(--xp-gray);
        }

        .seat.active .seat-avatar { background: var(--xp-green); }
        .seat.idle .seat-avatar { background: var(--xp-yellow); }
        .seat.maintenance .seat-avatar { background: var(--xp-red); }

        .seat-name {
            font-size: 0.65rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .seat-status {
            font-size: 0.55rem;
            padding: 0.1rem 0.3rem;
            border-radius: 999px;
            font-weight: 600;
        }

        .seat.active .seat-status { background: rgba(16, 185, 129, 0.2); color: var(--xp-green); }
        .seat.idle .seat-status { background: rgba(245, 158, 11, 0.2); color: var(--xp-yellow); }
        .seat.offline .seat-status { background: rgba(100, 116, 139, 0.2); color: var(--xp-gray); }
        .seat.maintenance .seat-status { background: rgba(239, 68, 68, 0.2); color: var(--xp-red); }

        .seat-pulse {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--xp-green);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        /* Recent Scans */
        .recent-scans {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--xp-border);
        }

        .recent-scans-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--xp-gray);
            margin-bottom: 0.5rem;
        }

        .recent-scan {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0;
            font-size: 0.7rem;
        }

        .recent-scan .time {
            font-family: 'JetBrains Mono', monospace;
            color: var(--xp-gray);
            min-width: 45px;
        }

        .recent-scan .name {
            flex: 1;
            font-weight: 500;
        }

        .recent-scan .type {
            font-size: 0.6rem;
            padding: 0.1rem 0.3rem;
            border-radius: 999px;
        }

        .recent-scan .type.in { background: rgba(16, 185, 129, 0.2); color: var(--xp-green); }
        .recent-scan .type.out { background: rgba(245, 158, 11, 0.2); color: var(--xp-yellow); }

        /* Floor selector */
        .floor-selector {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .floor-btn {
            padding: 0.35rem 0.75rem;
            border: 1px solid var(--xp-border);
            border-radius: 0.35rem;
            background: transparent;
            color: var(--xp-gray);
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
        }

        .floor-btn.active {
            border-color: #6366f1;
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .seat-grid { grid-template-columns: repeat(5, 1fr); }
        }

        @media (max-width: 992px) {
            .kiosk-main { grid-template-columns: 1fr; }
            .scanner-panel { order: -1; }
            .seat-grid { grid-template-columns: repeat(4, 1fr); }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="kiosk-header">
        <div class="kiosk-brand">
            <span style="font-size: 1.5rem;">🧪</span>
            <div>
                <h1>XPLabs Kiosk</h1>
                <span style="font-size: 0.65rem; color: var(--xp-gray);">Computer Lab Management</span>
            </div>
        </div>
        <div class="text-center">
            <div class="kiosk-time" id="kiosk-time">00:00:00</div>
            <div class="kiosk-date" id="kiosk-date">Loading...</div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="stat-badge">
                <span class="stat-dot active"></span>
                <span><?= $stats['active'] ?> Active</span>
            </div>
            <div class="stat-badge">
                <span class="stat-dot idle"></span>
                <span><?= $stats['idle'] ?> Idle</span>
            </div>
            <div class="stat-badge">
                <span class="stat-dot offline"></span>
                <span><?= $stats['offline'] ?> Offline</span>
            </div>
        </div>
    </div>

    <!-- Main -->
    <div class="kiosk-main">
        <!-- Scanner Panel -->
        <div class="scanner-panel">
            <div class="scanner-title">📷 QR Scanner</div>

            <!-- Mode Toggle -->
            <div class="mode-toggle">
                <button class="mode-btn active" id="btn-checkin" onclick="setMode('checkin')">
                    ✅ Check In
                </button>
                <button class="mode-btn" id="btn-checkout" onclick="setMode('checkout')">
                    🚪 Check Out
                </button>
            </div>

            <!-- Scanner Input -->
            <input type="text"
                   class="scanner-input"
                   id="scanner-input"
                   placeholder="Scan QR code or type LRN..."
                   autofocus
                   autocomplete="off">

            <!-- Scan Result -->
            <div class="scan-result" id="scan-result">
                <div class="name" id="result-name"></div>
                <div class="message" id="result-message"></div>
                <div class="points" id="result-points"></div>
            </div>

            <!-- Active Sessions -->
            <?php if (!empty($activeSessions)): ?>
            <div class="mt-3">
                <div class="scanner-title">📋 Active Sessions</div>
                <?php foreach ($activeSessions as $session): ?>
                <div class="d-flex justify-content-between align-items-center py-1" style="font-size: 0.75rem;">
                    <span><?= e($session['course_name']) ?></span>
                    <span class="badge bg-success"><?= date('H:i', strtotime($session['created_at'])) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Recent Scans -->
            <div class="recent-scans">
                <div class="recent-scans-title">Recent Scans</div>
                <div id="recent-scans-list">
                    <div class="text-muted" style="font-size: 0.7rem;">No scans yet</div>
                </div>
            </div>
        </div>

        <!-- Floor Plan Panel -->
        <div class="floor-panel">
            <div class="floor-header">
                <div class="floor-title">📐 Lab Floor Plan</div>
                <div class="floor-stats">
                    <div class="stat-badge">
                        <span class="stat-dot active"></span>
                        <span><?= $stats['active'] ?></span>
                    </div>
                    <div class="stat-badge">
                        <span class="stat-dot idle"></span>
                        <span><?= $stats['idle'] ?></span>
                    </div>
                    <div class="stat-badge">
                        <span class="stat-dot offline"></span>
                        <span><?= $stats['offline'] ?></span>
                    </div>
                    <div class="stat-badge">
                        <span class="stat-dot maintenance"></span>
                        <span><?= $stats['maintenance'] ?></span>
                    </div>
                </div>
            </div>

            <!-- Floor Selector -->
            <?php if (count($floors) > 1): ?>
            <div class="floor-selector">
                <?php foreach ($floors as $floor): ?>
                <a href="?floor_id=<?= $floor['id'] ?>" class="floor-btn <?= $floor['id'] == $selectedFloor ? 'active' : '' ?>"><?= e($floor['name']) ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Board -->
            <div class="lab-board">
                <span>📋</span>
                <span>TEACHER'S BOARD / PROJECTOR</span>
            </div>

            <!-- Seat Grid -->
            <div class="seat-grid" id="seat-grid">
                <?php foreach ($stations as $s):
                    $status = $s['is_maintenance'] ? 'maintenance' : ($s['status'] ?? 'offline');
                    $user = !empty($s['first_name']) ? trim($s['first_name'] . ' ' . $s['last_name']) : null;
                    $initial = $user ? strtoupper($user[0]) : '–';
                    $displayName = $user ?: ($status === 'maintenance' ? 'MAINT' : 'FREE');
                ?>
                <div class="seat <?= $status ?>" id="seat-<?= $s['id'] ?>" data-user-id="<?= $s['user_id'] ?? '' ?>">
                    <?php if ($status === 'active'): ?>
                    <div class="seat-pulse"></div>
                    <?php endif; ?>
                    <div class="seat-code"><?= e($s['station_code']) ?></div>
                    <div class="seat-avatar"><?= $initial ?></div>
                    <div class="seat-name" title="<?= e($displayName) ?>"><?= e($displayName) ?></div>
                    <div class="seat-status"><?= strtoupper($status) ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($stations)): ?>
                <div class="col-12 text-center text-muted py-5">
                    <p>No stations configured</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Clock
    function updateClock() {
        const now = new Date();
        document.getElementById('kiosk-time').textContent = now.toLocaleTimeString('en-US', { hour12: false });
        document.getElementById('kiosk-date').textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }
    updateClock();
    setInterval(updateClock, 1000);

    // Mode
    let currentMode = 'checkin';
    function setMode(mode) {
        currentMode = mode;
        document.getElementById('btn-checkin').classList.toggle('active', mode === 'checkin');
        document.getElementById('btn-checkout').classList.toggle('active', mode === 'checkout');
        document.getElementById('btn-checkout').classList.toggle('checkout', mode === 'checkout');
        const input = document.getElementById('scanner-input');
        input.classList.toggle('checkout-mode', mode === 'checkout');
        input.placeholder = mode === 'checkin' ? 'Scan QR code or type LRN...' : 'Scan QR to check out...';
        input.focus();
    }

    // Scanner input
    const scannerInput = document.getElementById('scanner-input');
    let inputBuffer = '';
    let inputTimeout = null;

    scannerInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const lrn = scannerInput.value.trim();
            if (lrn) {
                processScan(lrn);
                scannerInput.value = '';
            }
        }
    });

    // Keep focus on input
    document.addEventListener('click', function() {
        scannerInput.focus();
    });

    // Process scan
    async function processScan(lrn) {
        const resultEl = document.getElementById('scan-result');
        const nameEl = document.getElementById('result-name');
        const msgEl = document.getElementById('result-message');
        const pointsEl = document.getElementById('result-points');

        try {
            const endpoint = currentMode === 'checkin' ? '/api/attendance/qr-checkin' : '/api/attendance/qr-checkout';
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lrn })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                resultEl.className = 'scan-result success';
                nameEl.textContent = `✅ ${data.user?.first_name || ''} ${data.user?.last_name || ''}`;
                msgEl.textContent = currentMode === 'checkin' ? 'Checked in successfully!' : 'Checked out successfully!';
                pointsEl.textContent = data.points_earned ? `+${data.points_earned} points earned!` : '';

                // Highlight seat
                if (currentMode === 'checkin' && data.user?.id) {
                    const seat = document.querySelector(`[data-user-id="${data.user.id}"]`);
                    if (seat) {
                        seat.classList.add('just-scanned');
                        setTimeout(() => seat.classList.remove('just-scanned'), 1000);
                    }
                }

                // Add to recent scans
                addRecentScan(data.user, currentMode);
            } else {
                resultEl.className = 'scan-result error';
                nameEl.textContent = '❌ Error';
                msgEl.textContent = data.error || 'Scan failed';
                pointsEl.textContent = '';
            }
        } catch (err) {
            resultEl.className = 'scan-result error';
            nameEl.textContent = '❌ Network Error';
            msgEl.textContent = 'Could not connect to server';
            pointsEl.textContent = '';
        }

        // Auto-hide result after 5 seconds
        setTimeout(() => {
            resultEl.className = 'scan-result';
        }, 5000);
    }

    // Recent scans
    const recentScans = [];
    function addRecentScan(user, mode) {
        if (!user) return;
        const name = `${user.first_name || ''} ${user.last_name || ''}`.trim();
        const time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });

        recentScans.unshift({ name, mode, time });
        if (recentScans.length > 10) recentScans.pop();

        renderRecentScans();
    }

    function renderRecentScans() {
        const container = document.getElementById('recent-scans-list');
        if (recentScans.length === 0) {
            container.innerHTML = '<div class="text-muted" style="font-size: 0.7rem;">No scans yet</div>';
            return;
        }

        container.innerHTML = recentScans.map(scan => `
            <div class="recent-scan">
                <span class="time">${scan.time}</span>
                <span class="name">${scan.name}</span>
                <span class="type ${scan.mode === 'checkin' ? 'in' : 'out'}">${scan.mode === 'checkin' ? 'IN' : 'OUT'}</span>
            </div>
        `).join('');
    }

    // Auto-refresh floor plan every 10 seconds
    setInterval(async function() {
        try {
            const response = await fetch('/api/lab/stations<?= $selectedFloor ? "?floor_id=$selectedFloor" : "" ?>');
            if (!response.ok) return;
            const data = await response.json();

            // Update seats
            data.stations?.forEach(s => {
                const seat = document.getElementById(`seat-${s.id}`);
                if (!seat) return;

                seat.className = `seat ${s.status}`;
                const avatar = seat.querySelector('.seat-avatar');
                const name = seat.querySelector('.seat-name');
                const status = seat.querySelector('.seat-status');

                if (avatar) {
                    const initial = s.user ? s.user.charAt(0).toUpperCase() : '–';
                    avatar.textContent = initial;
                }
                if (name) {
                    const displayName = s.user || (s.status === 'maintenance' ? 'MAINT' : 'FREE');
                    name.textContent = displayName;
                    name.title = displayName;
                }
                if (status) {
                    status.textContent = s.status.toUpperCase();
                }

                // Add/remove pulse
                const existingPulse = seat.querySelector('.seat-pulse');
                if (s.status === 'active' && !existingPulse) {
                    const pulse = document.createElement('div');
                    pulse.className = 'seat-pulse';
                    seat.appendChild(pulse);
                } else if (s.status !== 'active' && existingPulse) {
                    existingPulse.remove();
                }
            });
        } catch (err) {
            // Silently fail
        }
    }, 10000);
    </script>
</body>
</html>