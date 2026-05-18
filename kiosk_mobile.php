<?php
/**
 * XPLabs - Mobile browser kiosk: camera QR scan + kiosk token auth.
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Services\LabService;

$labService = new LabService();
$floors = $labService->getFloors();
$defaultFloor = (int) ($floors[0]['id'] ?? 0);
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$pairPrefill = isset($_GET['pair']) ? trim((string) $_GET['pair']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <title>XPLabs — Mobile Kiosk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --border: #334155; --accent: #22c55e; --muted: #94a3b8; }
        body { background: var(--bg); color: #e2e8f0; min-height: 100vh; padding-bottom: env(safe-area-inset-bottom); }
        .k-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 1rem; }
        #reader { border-radius: 12px; overflow: hidden; max-width: 100%; min-height: 220px; background: #000; }
        .btn-xl { padding: 0.85rem 1rem; font-weight: 600; border-radius: 12px; }
        .status-ok { color: var(--accent); }
        .status-err { color: #f87171; }
        .touch-row { display: flex; gap: 0.5rem; }
        .touch-row .btn { flex: 1; }
    </style>
</head>
<body>
<div class="container py-3 px-3" style="max-width: 480px;">
    <h1 class="h5 mb-3">Lab kiosk</h1>

    <div class="k-card mb-3" id="pair-panel">
        <div class="small text-muted mb-2">Pairing (first-time or after token reset)</div>
        <label class="form-label small mb-1">Pairing code</label>
        <input type="text" class="form-control form-control-lg font-monospace mb-2" id="pair-input"
               placeholder="8-character code" autocomplete="off" value="<?= e($pairPrefill) ?>"
               style="background: #0f172a; border-color: var(--border); color: #fff;">
        <button type="button" class="btn btn-primary btn-xl w-100" id="btn-pair">Save pairing</button>
        <div class="small mt-2" id="pair-msg"></div>
    </div>

    <div class="k-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small text-muted">Session</span>
            <span class="badge bg-secondary" id="token-badge">No token</span>
        </div>
        <button type="button" class="btn btn-outline-danger btn-sm w-100 mb-2" id="btn-clear-token">Clear stored token</button>
        <label class="form-label small mb-1">Floor override (optional)</label>
        <select class="form-select" id="floor-select" style="background: #0f172a; border-color: var(--border); color: #fff;">
            <?php foreach ($floors as $fl): ?>
                <option value="<?= (int) $fl['id'] ?>" <?= ((int) $fl['id'] === $defaultFloor) ? 'selected' : '' ?>><?= e($fl['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="touch-row mb-3">
        <button type="button" class="btn btn-success btn-xl active" id="btn-checkin" data-mode="checkin">Check in</button>
        <button type="button" class="btn btn-outline-warning btn-xl" id="btn-checkout" data-mode="checkout">Check out</button>
    </div>

    <div class="k-card mb-3">
        <div class="small text-muted mb-2">Camera</div>
        <div id="reader"></div>
        <div class="small mt-2 text-center text-muted" id="cam-status">Starting camera…</div>
    </div>

    <div class="k-card">
        <div class="small text-muted mb-1">Last result</div>
        <div id="result-title" class="fw-semibold"></div>
        <div id="result-body" class="small mt-1"></div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode"></script>
<script>
(function () {
    const APP_BASE = <?= json_encode($appBase) ?>;
    const LS_KEY = 'xplabs_kiosk_token';
    const LS_FLOOR = 'xplabs_kiosk_floor_id';

    function apiUrl(path) {
        const p = path.startsWith('/') ? path : '/' + path;
        return APP_BASE + p;
    }

    let mode = 'checkin';
    let html5Qr = null;
    let scanning = true;

    function getToken() {
        try { return localStorage.getItem(LS_KEY) || ''; } catch (e) { return ''; }
    }

    function setToken(t) {
        try {
            if (t) localStorage.setItem(LS_KEY, t);
            else localStorage.removeItem(LS_KEY);
        } catch (e) {}
        updateBadge();
    }

    function updateBadge() {
        const t = getToken();
        const el = document.getElementById('token-badge');
        if (t) {
            el.textContent = 'Token saved';
            el.className = 'badge bg-success';
            document.getElementById('pair-panel').classList.add('opacity-50');
        } else {
            el.textContent = 'No token';
            el.className = 'badge bg-secondary';
            document.getElementById('pair-panel').classList.remove('opacity-50');
        }
    }

    function parseLrn(text) {
        const t = String(text || '').trim();
        if (!t) return '';
        try {
            const j = JSON.parse(t);
            if (j && typeof j.lrn === 'string') return j.lrn.trim();
        } catch (e) {}
        return t;
    }

    function floorId() {
        const sel = document.getElementById('floor-select');
        const v = parseInt(sel.value, 10);
        return Number.isFinite(v) ? v : 0;
    }

    async function doPair() {
        const code = document.getElementById('pair-input').value.trim().toUpperCase();
        const msg = document.getElementById('pair-msg');
        msg.textContent = '';
        if (code.length < 6) {
            msg.textContent = 'Enter the pairing code from Lab Management.';
            msg.className = 'small status-err';
            return;
        }
        try {
            const res = await fetch(apiUrl('/api/kiosk/pair.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pairing_code: code })
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                msg.textContent = data.error || 'Pairing failed';
                msg.className = 'small status-err';
                return;
            }
            setToken(data.token);
            if (data.floor_id) {
                const sel = document.getElementById('floor-select');
                sel.value = String(data.floor_id);
                try { localStorage.setItem(LS_FLOOR, String(data.floor_id)); } catch (e) {}
            }
            msg.textContent = 'Paired — token saved on this device.';
            msg.className = 'small status-ok';
            document.getElementById('pair-input').value = '';
        } catch (e) {
            msg.textContent = 'Network error';
            msg.className = 'small status-err';
        }
    }

    function setResult(title, body, ok) {
        document.getElementById('result-title').textContent = title || '';
        document.getElementById('result-body').textContent = body || '';
        document.getElementById('result-title').className = 'fw-semibold ' + (ok ? 'status-ok' : 'status-err');
    }

    async function handleScan(decodedText) {
        if (!scanning) return;
        const token = getToken();
        if (!token) {
            setResult('Not paired', 'Complete pairing above or ask Lab Management for a token.', false);
            return;
        }
        const lrn = parseLrn(decodedText);
        if (!lrn) {
            setResult('Invalid QR', 'Could not read LRN from scan.', false);
            return;
        }

        scanning = false;
        const fid = floorId();
        const headers = {
            'Content-Type': 'application/json',
            'X-Kiosk-Token': token
        };

        try {
            if (mode === 'checkin') {
                const payload = { lrn, floor_id: fid || undefined };
                const res = await fetch(apiUrl('/api/kiosk/unlock.php'), {
                    method: 'POST',
                    headers,
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    const name = (data.user && (data.user.first_name + ' ' + data.user.last_name).trim()) || lrn;
                    setResult('Welcome', name + ' — ' + (data.message || 'OK'), true);
                } else {
                    setResult('Check-in failed', data.error || res.statusText, false);
                }
            } else {
                const res = await fetch(apiUrl('/api/attendance/qr-checkout.php'), {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({ lrn })
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    setResult('Checked out', data.message || 'OK', true);
                } else {
                    setResult('Check-out failed', data.error || res.statusText, false);
                }
            }
        } catch (e) {
            setResult('Error', String(e.message || e), false);
        }

        setTimeout(() => { scanning = true; }, 1200);
    }

    document.getElementById('btn-checkin').addEventListener('click', function () {
        mode = 'checkin';
        document.getElementById('btn-checkin').className = 'btn btn-success btn-xl active';
        document.getElementById('btn-checkout').className = 'btn btn-outline-warning btn-xl';
    });
    document.getElementById('btn-checkout').addEventListener('click', function () {
        mode = 'checkout';
        document.getElementById('btn-checkout').className = 'btn btn-warning btn-xl active';
        document.getElementById('btn-checkin').className = 'btn btn-outline-success btn-xl';
    });

    document.getElementById('btn-pair').addEventListener('click', doPair);
    document.getElementById('btn-clear-token').addEventListener('click', function () {
        setToken('');
        setResult('Token cleared', 'Pair again or paste a new token.', false);
    });

    // Restore floor preference
    try {
        const sf = localStorage.getItem(LS_FLOOR);
        if (sf && document.getElementById('floor-select').querySelector('option[value="' + sf + '"]')) {
            document.getElementById('floor-select').value = sf;
        }
    } catch (e) {}
    document.getElementById('floor-select').addEventListener('change', function () {
        try { localStorage.setItem(LS_FLOOR, document.getElementById('floor-select').value); } catch (e) {}
    });

    updateBadge();

    const camStatus = document.getElementById('cam-status');
    if (typeof Html5Qrcode === 'undefined') {
        camStatus.textContent = 'Scanner library failed to load.';
        camStatus.className = 'small mt-2 text-center status-err';
        return;
    }

    html5Qr = new Html5Qrcode('reader');
    const config = { fps: 8, qrbox: { width: 240, height: 240 } };

    function startCam(constraints) {
        return html5Qr.start(
            constraints,
            config,
            (text) => { handleScan(text); },
            () => {}
        );
    }

    startCam({ facingMode: 'environment' }).then(() => {
        camStatus.textContent = 'Point at student QR';
        camStatus.className = 'small mt-2 text-center text-muted';
    }).catch(() => {
        startCam({ facingMode: 'user' }).then(() => {
            camStatus.textContent = 'Point at student QR';
            camStatus.className = 'small mt-2 text-center text-muted';
        }).catch(() => {
            camStatus.textContent = 'Camera permission denied or unavailable.';
            camStatus.className = 'small mt-2 text-center status-err';
        });
    });
})();
</script>
</body>
</html>
