<?php
/**
 * XPLabs - Admin: customize quiz powerup shop catalog
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::requireRole('admin');

$db = Database::getInstance();
$message = null;

$categories = ['timer', 'hints', 'scoring', 'skip', 'exemption', 'privilege', 'other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $code = strtolower(trim((string) ($_POST['code'] ?? '')));
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $icon = trim((string) ($_POST['icon'] ?? ''));
            $pointCost = max(0, (int) ($_POST['point_cost'] ?? 0));
            $type = (string) ($_POST['type'] ?? 'quiz');
            $category = trim((string) ($_POST['category'] ?? ''));
            $configRaw = trim((string) ($_POST['config'] ?? ''));
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($code === '' || !preg_match('/^[a-z0-9_]+$/', $code)) {
                throw new RuntimeException('Code is required (lowercase letters, numbers, underscore only).');
            }
            if ($name === '') {
                throw new RuntimeException('Name is required.');
            }
            if (!in_array($type, ['quiz', 'reward'], true)) {
                throw new RuntimeException('Invalid type.');
            }
            $config = null;
            if ($configRaw !== '') {
                $decoded = json_decode($configRaw, true);
                if (!is_array($decoded)) {
                    throw new RuntimeException('Config must be valid JSON object.');
                }
                $config = json_encode($decoded, JSON_UNESCAPED_UNICODE);
            }

            $row = [
                'code' => $code,
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'icon' => $icon !== '' ? $icon : null,
                'point_cost' => $pointCost,
                'type' => $type,
                'category' => $category !== '' ? $category : null,
                'config' => $config,
                'is_active' => $isActive,
            ];

            if ($id > 0) {
                $dup = $db->fetch('SELECT id FROM powerups WHERE code = ? AND id <> ?', [$code, $id]);
                if ($dup) {
                    throw new RuntimeException('Code already used by another powerup.');
                }
                $db->update('powerups', $row, 'id = ?', [$id]);
                $message = ['type' => 'success', 'text' => 'Powerup updated.'];
            } else {
                $dup = $db->fetch('SELECT id FROM powerups WHERE code = ?', [$code]);
                if ($dup) {
                    throw new RuntimeException('Code already exists.');
                }
                $db->insert('powerups', $row);
                $message = ['type' => 'success', 'text' => 'Powerup created.'];
            }
        } elseif ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            $active = (int) ($_POST['is_active'] ?? 0) ? 1 : 0;
            if ($id <= 0) {
                throw new RuntimeException('Invalid powerup.');
            }
            $db->update('powerups', ['is_active' => $active], 'id = ?', [$id]);
            $message = ['type' => 'success', 'text' => $active ? 'Powerup enabled in shop.' : 'Powerup hidden from shop.'];
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid powerup.');
            }
            $db->query('DELETE FROM powerups WHERE id = ?', [$id]);
            $message = ['type' => 'success', 'text' => 'Powerup deleted.'];
        }
    } catch (Throwable $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}

$powerups = $db->fetchAll(
    "SELECT * FROM powerups WHERE type = 'quiz' ORDER BY is_active DESC, point_cost ASC, name ASC"
);
$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    foreach ($powerups as $p) {
        if ((int) $p['id'] === $editId) {
            $editing = $p;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Powerup Shop - Admin - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --border: #334155; --text: #e2e8f0; --muted: #94a3b8; }
        body { background: var(--bg); color: var(--text); }
        .main-content { margin-left: 260px; padding: 2rem; }
        .xp-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; }
        .table { color: var(--text); }
        .table td, .table th { border-color: var(--border); vertical-align: middle; }
        .form-control, .form-select { background: #0f172a; border-color: var(--border); color: var(--text); }
        .form-control:focus, .form-select:focus { background: #0f172a; color: var(--text); border-color: #6366f1; box-shadow: 0 0 0 .2rem rgba(99,102,241,.25); }
        code { color: #a5b4fc; }
    </style>
</head>
<body>
<?php include __DIR__ . '/components/admin_sidebar.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="mb-1"><i class="bi bi-gem me-2"></i>Powerup Shop</h2>
            <p class="text-muted mb-0">Customize items shown in <a href="powerup_shop.php" class="link-info" target="_blank">student shop</a> and quiz store.</p>
        </div>
        <a href="admin_powerup_shop.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New powerup</a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['text']) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="xp-card p-3">
                <h5 class="mb-3"><?= $editing ? 'Edit powerup' : 'Add powerup' ?></h5>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                    <div class="mb-2">
                        <label class="form-label small">Code</label>
                        <input class="form-control form-control-sm" name="code" required pattern="[a-z0-9_]+"
                               value="<?= e($editing['code'] ?? '') ?>" <?= $editing ? 'readonly' : '' ?>>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Name</label>
                        <input class="form-control form-control-sm" name="name" required value="<?= e($editing['name'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Icon (emoji)</label>
                        <input class="form-control form-control-sm" name="icon" maxlength="20" value="<?= e($editing['icon'] ?? '✨') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Description</label>
                        <textarea class="form-control form-control-sm" name="description" rows="2"><?= e($editing['description'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">Point cost</label>
                            <input type="number" min="0" class="form-control form-control-sm" name="point_cost" value="<?= (int) ($editing['point_cost'] ?? 50) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Category</label>
                            <select class="form-select form-select-sm" name="category">
                                <option value="">—</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat) ?>" <?= ($editing['category'] ?? '') === $cat ? 'selected' : '' ?>><?= e(ucfirst($cat)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Type</label>
                        <select class="form-select form-select-sm" name="type">
                            <option value="quiz" selected>quiz (shop)</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Config JSON <span class="text-muted">(effect rules)</span></label>
                        <textarea class="form-control form-control-sm font-monospace" name="config" rows="4" placeholder='{"effect":"mc_halve_choices","per_question_limit":1}'><?php
                            $cfg = $editing['config'] ?? '';
                            if (is_string($cfg) && $cfg !== '') {
                                $pretty = json_decode($cfg, true);
                                echo e($pretty ? json_encode($pretty, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $cfg);
                            }
                        ?></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= !isset($editing['is_active']) || !empty($editing['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active in shop</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                    <?php if ($editing): ?>
                    <a href="admin_powerup_shop.php" class="btn btn-outline-secondary btn-sm ms-1">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="xp-card p-3">
                <h5 class="mb-3">Catalog (<?= count($powerups) ?>)</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Code</th>
                                <th>Cost</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($powerups as $p): ?>
                            <tr>
                                <td><?= e(($p['icon'] ?: '✨') . ' ' . $p['name']) ?></td>
                                <td><code><?= e($p['code']) ?></code></td>
                                <td><?= (int) $p['point_cost'] ?> pts</td>
                                <td>
                                    <?php if (!empty($p['is_active'])): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-outline-light btn-sm" href="admin_powerup_shop.php?edit=<?= (int) $p['id'] ?>">Edit</a>
                                    <form method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= !empty($p['is_active']) ? '0' : '1' ?>">
                                        <button type="submit" class="btn btn-outline-warning btn-sm"><?= !empty($p['is_active']) ? 'Hide' : 'Show' ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($powerups)): ?>
                            <tr><td colspan="5" class="text-muted">No quiz powerups yet. Add one or run migration 047.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
