<?php
/**
 * XPLabs - Equipment/Inventory Management (Admin/Teacher)
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\InventoryService;
use XPLabs\Lib\Database;

Auth::requireRole(['admin', 'teacher']);

$db = Database::getInstance();
$role = $_SESSION['user_role'];
$userId = Auth::id();
$service = new InventoryService();

// Filters
$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$searchFilter = $_GET['search'] ?? '';

$items = $service->getItems(array_filter([
    'status' => $statusFilter,
    'category' => $categoryFilter,
    'search' => $searchFilter,
]));

// Get stats
$stats = $service->getStats();

// Get labs for dropdown
$labs = $db->fetchAll("SELECT * FROM labs WHERE is_active = 1 ORDER BY name ASC");

// Get users for assignment dropdown
$students = $db->fetchAll("SELECT id, lrn, first_name, last_name FROM users WHERE role = 'student' ORDER BY last_name ASC LIMIT 500");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - XPLabs</title>
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
            --orange: #f97316;
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

        .form-control, .form-select {
            background: var(--bg-dark); border: 1px solid var(--border); color: var(--text);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent); box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }
        .form-label { color: var(--text-muted); font-size: 0.85rem; }

        .stat-card {
            background: var(--bg-dark); border: 1px solid var(--border);
            border-radius: 8px; padding: 1rem; text-align: center;
        }
        .stat-card .value { font-size: 1.5rem; font-weight: 700; color: #fff; }
        .stat-card .label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; }

        .status-pill {
            display: inline-block; padding: 0.25rem 0.75rem;
            border-radius: 20px; font-size: 0.7rem; font-weight: 600;
        }
        .status-pill.available { background: rgba(34, 197, 94, 0.1); color: var(--green); }
        .status-pill.in_use { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .status-pill.maintenance { background: rgba(245, 158, 11, 0.1); color: var(--yellow); }
        .status-pill.damaged { background: rgba(239, 68, 68, 0.1); color: var(--red); }
        .status-pill.lost { background: rgba(100, 116, 139, 0.1); color: var(--text-muted); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/admin_sidebar.php'; ?>
<div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-box-seam me-2"></i>Inventory Management</h2>
                <p class="text-muted mb-0">Track equipment and lab resources</p>
            </div>
            <button class="btn btn-primary" onclick="openCreateModal()">
                <i class="bi bi-plus-lg me-1"></i> Add Item
            </button>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="value"><?= $stats['total'] ?? count($items) ?></div>
                    <div class="label">Total Items</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="value text-success"><?= $stats['available_count'] ?? 0 ?></div>
                    <div class="label">Available</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="value text-warning"><?= $stats['maintenance_count'] ?? 0 ?></div>
                    <div class="label">Maintenance</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="value" style="color: var(--red)"><?= $stats['damaged_count'] ?? 0 ?></div>
                    <div class="label">Damaged/Lost</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="xp-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Search items..." value="<?= e($searchFilter) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach (InventoryService::getCategories() as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $categoryFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="available" <?= $statusFilter === 'available' ? 'selected' : '' ?>>Available</option>
                            <option value="in_use" <?= $statusFilter === 'in_use' ? 'selected' : '' ?>>In Use</option>
                            <option value="maintenance" <?= $statusFilter === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                            <option value="damaged" <?= $statusFilter === 'damaged' ? 'selected' : '' ?>>Damaged</option>
                            <option value="lost" <?= $statusFilter === 'lost' ? 'selected' : '' ?>>Lost</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Filter</button>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <a href="inventory.php" class="btn btn-outline-secondary w-100">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Items Table -->
        <div class="xp-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Equipment Inventory</h5>
                <span class="text-muted small"><?= count($items) ?> items</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="xp-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Location</th>
                                <th>Assigned To</th>
                                <th>Condition</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): 
                                $statusClass = str_replace('-', '_', $item['status']);
                            ?>
                            <tr>
                                <td><code><?= e($item['item_code']) ?></code></td>
                                <td>
                                    <div class="fw-semibold"><?= e($item['name']) ?></div>
                                    <?php if ($item['brand'] || $item['model']): ?>
                                    <div class="text-muted small"><?= e($item['brand']) ?> <?= e($item['model']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(InventoryService::getCategories()[$item['category']] ?? $item['category']) ?></td>
                                <td><span class="status-pill <?= $statusClass ?>"><?= ucfirst(str_replace('_', ' ', $item['status'])) ?></span></td>
                                <td><?= e($item['lab_name'] ?? '-') ?></td>
                                <td>
                                    <?php if ($item['first_name']): ?>
                                    <?= e($item['first_name'] . ' ' . $item['last_name']) ?>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= ucfirst($item['condition_rating'] ?? 'good') ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?= htmlspecialchars(json_encode($item)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteItem(<?= $item['id'] ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No inventory items found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="modalItem" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form id="itemForm" class="modal-content">
                <input type="hidden" name="action" id="item-action" value="create">
                <input type="hidden" name="item_id" id="item-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title">Add Equipment Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Item Code</label>
                            <input type="text" name="item_code" id="item-code" class="form-control" placeholder="Auto-generated if blank">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Item Name *</label>
                            <input type="text" name="name" id="item-name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category *</label>
                            <select name="category" id="item-category" class="form-select" required>
                                <?php foreach (InventoryService::getCategories() as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" id="item-status" class="form-select">
                                <option value="available" selected>Available</option>
                                <option value="in_use">In Use</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="damaged">Damaged</option>
                                <option value="lost">Lost</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Condition</label>
                            <select name="condition_rating" id="item-condition" class="form-select">
                                <option value="excellent">Excellent</option>
                                <option value="good" selected>Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand" id="item-brand" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Model</label>
                            <input type="text" name="model" id="item-model" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Serial Number</label>
                            <input type="text" name="serial_number" id="item-serial" class="form-control">
                        </div>
                        <div class="col-md-6" id="assign-field" style="display: none;">
                            <label class="form-label">Assign To</label>
                            <select name="user_id" id="item-user" class="form-select">
                                <option value="">Unassign</option>
                                <?php foreach ($students as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= e($s['lrn'] . ' - ' . $s['first_name'] . ' ' . $s['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="item-description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" id="item-notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function openCreateModal() {
        document.getElementById('itemForm').reset();
        document.getElementById('item-action').value = 'create';
        document.getElementById('item-id').value = '';
        document.getElementById('modal-title').textContent = 'Add Equipment Item';
        document.getElementById('assign-field').style.display = 'none';
        new bootstrap.Modal(document.getElementById('modalItem')).show();
    }

    function openEditModal(item) {
        document.getElementById('item-action').value = 'update';
        document.getElementById('item-id').value = item.id;
        document.getElementById('item-code').value = item.item_code || '';
        document.getElementById('item-name').value = item.name;
        document.getElementById('item-category').value = item.category;
        document.getElementById('item-status').value = item.status;
        document.getElementById('item-condition').value = item.condition_rating || 'good';
        document.getElementById('item-brand').value = item.brand || '';
        document.getElementById('item-model').value = item.model || '';
        document.getElementById('item-serial').value = item.serial_number || '';
        document.getElementById('item-description').value = item.description || '';
        document.getElementById('item-notes').value = item.notes || '';
        if (item.assigned_to) {
            document.getElementById('item-user').value = item.assigned_to;
            document.getElementById('assign-field').style.display = 'block';
        } else {
            document.getElementById('assign-field').style.display = 'block';
        }
        document.getElementById('modal-title').textContent = 'Edit Item: ' + item.name;
        new bootstrap.Modal(document.getElementById('modalItem')).show();
    }

    document.getElementById('itemForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData);
        
        try {
            const response = await fetch('/api/inventory/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            
            if (response.ok && result.success) {
                alert(result.message);
                location.reload();
            } else {
                alert('Error: ' + (result.error || 'Unknown error'));
            }
        } catch (err) {
            alert('Network error');
        }
    });

    async function deleteItem(id) {
        if (!confirm('Are you sure you want to delete this item?')) return;
        
        try {
            const response = await fetch('/api/inventory/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', item_id: id })
            });
            const result = await response.json();
            
            if (response.ok && result.success) {
                alert(result.message);
                location.reload();
            } else {
                alert('Error: ' + (result.error || 'Unknown error'));
            }
        } catch (err) {
            alert('Network error');
        }
    }
    </script>
</body>
</html>