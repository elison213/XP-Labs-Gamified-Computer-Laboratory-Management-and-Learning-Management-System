<?php
/**
 * XPLabs - Powerup Shop (Student)
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Services\PointService;

Auth::requireRole('student');
$userId = Auth::id();
$db = Database::getInstance();
$pointService = new PointService();

$pointBalance = $pointService->getBalance($userId);
$shopPowerups = $db->fetchAll(
    "SELECT id, code, name, description, icon, point_cost, config
     FROM powerups
     WHERE type = 'quiz' AND is_active = 1
     ORDER BY point_cost ASC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Powerup Shop - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-main: #0f172a;
            --bg-card: #1e293b;
            --bg-dark: #0b1220;
            --border: #334155;
            --text: #e2e8f0;
            --text-muted: #94a3b8;
            --accent: #6366f1;
            --green: #22c55e;
            --yellow: #eab308;
        }
        body { background: var(--bg-main); color: var(--text); font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; min-height: 100vh; }
        .main-content { margin-left: 260px; padding: 2rem; }
        .xp-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .xp-card .card-header { background: transparent; border-bottom: 1px solid var(--border); padding: 1rem 1.5rem; }
        .xp-card .card-body { padding: 1.5rem; }
        .points-hud {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.6rem 0.9rem; border-radius: 999px;
            background: linear-gradient(135deg, rgba(234, 179, 8, 0.25), rgba(245, 158, 11, 0.15));
            border: 1px solid rgba(234, 179, 8, 0.55); color: #fde68a; font-weight: 900;
        }
        .points-hud .pts { font-size: 1.2rem; line-height: 1; }
        .shop-item { background: var(--bg-dark); border: 1px solid var(--border); border-radius: 12px; padding: 1rem; }
        .cost-badge { font-weight: 800; }
        .affordable { background: rgba(34,197,94,.15); border-color: rgba(34,197,94,.35); }
        .not-affordable { opacity: .85; }
        .code-pill { font-size: .75rem; color: var(--text-muted); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/student_sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="mb-1"><i class="bi bi-gem me-2"></i>Powerup Shop</h2>
                <p class="text-muted mb-0">Powerups are purchased and used during a quiz attempt.</p>
            </div>
            <span class="points-hud" title="Your current points">
                <i class="bi bi-stars"></i>
                <span class="pts"><?= (int) $pointBalance ?></span>
                <span>PTS</span>
            </span>
        </div>

        <div class="xp-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-shop me-2"></i>Catalog</h5>
                <a href="my_quizzes.php" class="btn btn-sm btn-outline-light"><i class="bi bi-play-fill me-1"></i>Go to Quizzes</a>
            </div>
            <div class="card-body">
                <div class="alert alert-info small">
                    To use a powerup: start a quiz → pick a question → press the powerup button on the right-side menu.
                    If you have no stock, the button shows <strong>+</strong> and opens the store.
                </div>

                <?php if (empty($shopPowerups)): ?>
                    <div class="text-muted">No active quiz powerups configured.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($shopPowerups as $p): ?>
                            <?php $canAfford = $pointBalance >= (int) $p['point_cost']; ?>
                            <div class="col-12 col-md-6">
                                <div class="shop-item <?= $canAfford ? 'affordable' : 'not-affordable' ?>">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-bold"><?= e(($p['icon'] ?: '✨') . ' ' . $p['name']) ?></div>
                                            <div class="text-muted small"><?= e($p['description'] ?? '') ?></div>
                                            <div class="code-pill mt-1"><i class="bi bi-code-slash me-1"></i><?= e($p['code']) ?></div>
                                        </div>
                                        <span class="badge <?= $canAfford ? 'bg-success' : 'bg-secondary' ?> cost-badge">
                                            <?= (int) $p['point_cost'] ?> pts
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

