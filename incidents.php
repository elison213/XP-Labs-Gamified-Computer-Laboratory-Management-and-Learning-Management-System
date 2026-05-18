<?php
/**
 * XPLabs - Incident Management (Admin/Teacher)
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\IncidentService;
use XPLabs\Lib\Database;

Auth::requireRole(['admin', 'teacher']);

$db = Database::getInstance();
$role = $_SESSION['user_role'];
$userId = Auth::id();
$service = new IncidentService();

// Filters
$statusFilter = $_GET['status'] ?? '';
$severityFilter = $_GET['severity'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$labFilter = $_GET['lab_id'] ?? '';
$assigneeFilter = $_GET['assigned_to'] ?? '';
$dateFromFilter = $_GET['date_from'] ?? '';
$dateToFilter = $_GET['date_to'] ?? '';
$searchFilter = trim((string) ($_GET['search'] ?? ''));

$incidents = $service->getIncidents(array_filter([
    'status' => $statusFilter,
    'severity' => $severityFilter,
    'type' => $typeFilter,
    'lab_id' => $labFilter !== '' ? (int) $labFilter : null,
    'assigned_to' => $assigneeFilter !== '' ? (int) $assigneeFilter : null,
    'date_from' => $dateFromFilter !== '' ? $dateFromFilter : null,
    'date_to' => $dateToFilter !== '' ? $dateToFilter : null,
    'search' => $searchFilter !== '' ? $searchFilter : null,
], static fn ($v) => $v !== null && $v !== ''));

$labs = $db->fetchAll("SELECT id, name FROM labs WHERE is_active = 1 ORDER BY name ASC");
$assignees = $db->fetchAll("SELECT id, first_name, last_name FROM users WHERE role IN ('admin', 'teacher') ORDER BY last_name ASC, first_name ASC");
$stations = $db->fetchAll(
    "SELECT ls.id, ls.station_code, lf.name AS floor_name, l.name AS lab_name
     FROM lab_stations ls
     INNER JOIN lab_floors lf ON ls.floor_id = lf.id
     LEFT JOIN labs l ON lf.lab_id = l.id
     ORDER BY l.name, lf.name, ls.station_code
     LIMIT 800"
);

// Aggregate stats (all incidents; independent of current filters)
$stats = $service->getStats() ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Management - XPLabs</title>
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
            --orange: #f97316;
        }
        
        body {
            background: var(--bg-main);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }
        <?php if (($_SESSION['user_role'] ?? 'admin') === 'teacher'): ?>
        body { background: #f1f5f9 !important; }
        .main-content { background: #f1f5f9; }
        .xp-card { background: #fff; border-color: #e2e8f0; }
        .xp-card .card-header { background: #fff; border-color: #e2e8f0; }
        .xp-card .card-header h5 { color: #1e293b; }
        .xp-table th { color: #64748b; }
        .xp-table td { color: #1e293b; border-color: #e2e8f0; }
        .form-control, .form-select { background: #fff; border-color: #e2e8f0; color: #1e293b; }
        .form-label { color: #64748b; }
        .text-muted { color: #64748b !important; }
        .text-white { color: #1e293b !important; }
        .modal-content { background: #fff; }
        .modal-header { border-color: #e2e8f0; }
        .modal-footer { border-color: #e2e8f0; }
        <?php endif; ?>

        .sidebar {
            position: fixed; top: 0; left: 0; width: 260px; height: 100vh;
            background: var(--bg-card); border-right: 1px solid var(--border);
            z-index: 1000; overflow-y: auto;
        }
        .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .sidebar-brand h4 { margin: 0; font-weight: 700; color: var(--text); }
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
        .xp-card .card-header h5 { margin: 0; font-weight: 600; color: var(--text); }
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
            background: var(--bg-main); border: 1px solid var(--border); color: var(--text);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent); box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }
        .form-label { color: var(--text-muted); font-size: 0.85rem; }

        .stat-card {
            background: var(--bg-main); border: 1px solid var(--border);
            border-radius: 8px; padding: 1rem; text-align: center;
        }
        .stat-card .value { font-size: 1.5rem; font-weight: 700; color: var(--text); }
        .stat-card .label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; }

        .severity-badge {
            display: inline-block; padding: 0.2rem 0.6rem;
            border-radius: 999px; font-size: 0.7rem; font-weight: 600;
        }
        .severity-badge.low { background: rgba(34, 197, 94, 0.1); color: var(--green); }
        .severity-badge.medium { background: rgba(234, 179, 8, 0.1); color: var(--yellow); }
        .severity-badge.high { background: rgba(249, 115, 22, 0.1); color: var(--orange); }
        .severity-badge.critical { background: rgba(239, 68, 68, 0.1); color: var(--red); }

        .status-badge {
            display: inline-block; padding: 0.2rem 0.6rem;
            border-radius: 999px; font-size: 0.7rem; font-weight: 600;
        }
        .status-badge.reported { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .status-badge.investigating { background: rgba(245, 158, 11, 0.1); color: var(--yellow); }
        .status-badge.resolved { background: rgba(34, 197, 94, 0.1); color: var(--green); }
        .status-badge.dismissed { background: rgba(100, 116, 139, 0.1); color: var(--text-muted); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/admin_sidebar.php'; ?>
<div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-exclamation-triangle me-2"></i>Incident Management</h2>
                <p class="text-muted mb-0">Track and resolve lab incidents</p>
            </div>
            <button class="btn btn-primary" onclick="openCreateModal()">
                <i class="bi bi-plus-lg me-1"></i> Report Incident
            </button>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="value text-primary"><?= (int) ($stats['total'] ?? count($incidents)) ?></div>
                    <div class="label">Total (all time)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="value text-warning"><?= (int) ($stats['reported_count'] ?? 0) ?></div>
                    <div class="label">Reported</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="value" style="color: var(--orange)"><?= (int) ($stats['investigating_count'] ?? 0) ?></div>
                    <div class="label">Investigating</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="value text-success"><?= (int) ($stats['resolved_count'] ?? 0) ?></div>
                    <div class="label">Resolved</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="xp-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Search</label>
                        <input type="search" name="search" class="form-control" placeholder="Title, description, reporter, lab, station..." value="<?= e($searchFilter) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="reported" <?= $statusFilter === 'reported' ? 'selected' : '' ?>>Reported</option>
                            <option value="investigating" <?= $statusFilter === 'investigating' ? 'selected' : '' ?>>Investigating</option>
                            <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                            <option value="dismissed" <?= $statusFilter === 'dismissed' ? 'selected' : '' ?>>Dismissed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Severity</label>
                        <select name="severity" class="form-select">
                            <option value="">All Severity</option>
                            <option value="low" <?= $severityFilter === 'low' ? 'selected' : '' ?>>Low</option>
                            <option value="medium" <?= $severityFilter === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="high" <?= $severityFilter === 'high' ? 'selected' : '' ?>>High</option>
                            <option value="critical" <?= $severityFilter === 'critical' ? 'selected' : '' ?>>Critical</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Type</label>
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <?php foreach (\XPLabs\Services\IncidentService::getIncidentTypes() as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $typeFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Lab</label>
                        <select name="lab_id" class="form-select">
                            <option value="">All labs</option>
                            <?php foreach ($labs as $l): ?>
                            <option value="<?= (int) $l['id'] ?>" <?= (string) $labFilter === (string) $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Assignee</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">Anyone</option>
                            <?php foreach ($assignees as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= (string) $assigneeFilter === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['first_name'] . ' ' . $a['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= e($dateFromFilter) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= e($dateToFilter) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="incidents.php" class="btn btn-outline-secondary w-100">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Incidents Table -->
        <div class="xp-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Incident Log</h5>
                <span class="text-muted small"><?= count($incidents) ?> incidents</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="xp-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Reported By</th>
                                <th>Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidents as $inc): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($inc['title']) ?></div>
                                    <div class="text-muted small truncated"><?= e($inc['lab_name'] ?? $inc['location'] ?? '') ?></div>
                                </td>
                                <td><?= e(IncidentService::getIncidentTypes()[$inc['type']] ?? $inc['type']) ?></td>
                                <td><span class="severity-badge <?= $inc['severity'] ?>"><?= ucfirst($inc['severity']) ?></span></td>
                                <td><span class="status-badge <?= $inc['status'] ?>"><?= ucfirst($inc['status']) ?></span></td>
                                <td><?= e($inc['reporter_first'] . ' ' . $inc['reporter_last']) ?></td>
                                <td><?= date('M j, Y', strtotime($inc['created_at'])) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="viewIncident(<?= htmlspecialchars(json_encode($inc)) ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?= htmlspecialchars(json_encode($inc)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($incidents)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No incidents found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div class="modal fade" id="modalIncident" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form id="incidentForm" class="modal-content">
                <input type="hidden" name="action" id="incident-action" value="create">
                <input type="hidden" name="incident_id" id="incident-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title">Report Incident</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" id="incident-title" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Type *</label>
                            <select name="type" id="incident-type" class="form-select" required>
                                <?php foreach (IncidentService::getIncidentTypes() as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Severity *</label>
                            <select name="severity" id="incident-severity" class="form-select" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="incident-description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lab</label>
                            <select name="lab_id" id="incident-lab-id" class="form-select">
                                <option value="">—</option>
                                <?php foreach ($labs as $l): ?>
                                <option value="<?= (int) $l['id'] ?>"><?= e($l['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Station (optional)</label>
                            <select name="station_id" id="incident-station-id" class="form-select">
                                <option value="">—</option>
                                <?php foreach ($stations as $st): ?>
                                <option value="<?= (int) $st['id'] ?>"><?= e(($st['lab_name'] ?? '') . ' · ' . ($st['floor_name'] ?? '') . ' · ' . ($st['station_code'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="assign-field" style="display: none;">
                            <label class="form-label">Assignee</label>
                            <select name="assigned_to" id="incident-assigned-to" class="form-select">
                                <option value="">—</option>
                                <?php foreach ($assignees as $a): ?>
                                <option value="<?= (int) $a['id'] ?>"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="status-field" style="display: none;">
                            <label class="form-label">Status</label>
                            <select name="status" id="incident-status" class="form-select">
                                <option value="reported">Reported</option>
                                <option value="investigating">Investigating</option>
                                <option value="resolved">Resolved</option>
                                <option value="dismissed">Dismissed</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="resolution-field" style="display: none;">
                            <label class="form-label">Resolution Notes</label>
                            <textarea name="resolution_notes" id="incident-resolution-notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="delete-btn" style="display: none;" onclick="deleteIncident()">
                        <i class="bi bi-trash me-1"></i> Delete
                    </button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="modalView" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="view-title"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="view-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function openCreateModal() {
        document.getElementById('incidentForm').reset();
        document.getElementById('incident-action').value = 'create';
        document.getElementById('incident-id').value = '';
        document.getElementById('modal-title').textContent = 'Report Incident';
        document.getElementById('status-field').style.display = 'none';
        document.getElementById('resolution-field').style.display = 'none';
        document.getElementById('assign-field').style.display = 'none';
        document.getElementById('delete-btn').style.display = 'none';
        document.getElementById('incident-lab-id').value = '';
        document.getElementById('incident-station-id').value = '';
        new bootstrap.Modal(document.getElementById('modalIncident')).show();
    }

    function openEditModal(inc) {
        document.getElementById('incident-action').value = 'update';
        document.getElementById('incident-id').value = inc.id;
        document.getElementById('incident-title').value = inc.title;
        document.getElementById('incident-type').value = inc.type;
        document.getElementById('incident-severity').value = inc.severity;
        document.getElementById('incident-description').value = inc.description || '';
        document.getElementById('incident-status').value = inc.status;
        document.getElementById('incident-resolution-notes').value = inc.resolution_notes || '';
        document.getElementById('incident-lab-id').value = inc.lab_id || '';
        document.getElementById('incident-station-id').value = inc.station_id || '';
        document.getElementById('incident-assigned-to').value = inc.assigned_to || '';
        document.getElementById('modal-title').textContent = 'Edit Incident #' + inc.id;
        document.getElementById('status-field').style.display = 'block';
        document.getElementById('resolution-field').style.display = 'block';
        document.getElementById('assign-field').style.display = 'block';
        document.getElementById('delete-btn').style.display = 'inline-block';
        new bootstrap.Modal(document.getElementById('modalIncident')).show();
    }

    async function viewIncident(inc) {
        document.getElementById('view-title').textContent = 'Incident #' + inc.id + ': ' + inc.title;
        document.getElementById('view-body').innerHTML = '<p class="text-muted">Loading…</p>';
        new bootstrap.Modal(document.getElementById('modalView')).show();
        try {
            const res = await fetch('/api/incidents/detail.php?id=' + encodeURIComponent(inc.id));
            const data = await res.json();
            if (!data.success || !data.incident) {
                document.getElementById('view-body').innerHTML = '<p>Could not load incident.</p>';
                return;
            }
            const i = data.incident;
            const logs = data.logs || [];
            const esc = (s) => {
                const d = document.createElement('div');
                d.textContent = s == null ? '' : String(s);
                return d.innerHTML;
            };
            let logHtml = '';
            if (logs.length) {
                logHtml = '<div class="col-12 mt-3"><strong>Activity</strong><ul class="small mt-2 mb-0">';
                logs.forEach(l => {
                    logHtml += `<li>${esc(l.created_at)} — ${esc(l.first_name)} ${esc(l.last_name)}: ${esc(l.action)} ${esc(l.notes || '')}</li>`;
                });
                logHtml += '</ul></div>';
            }
            const sev = i.severity || '';
            const st = i.status || '';
            document.getElementById('view-body').innerHTML = `
            <div class="row g-3">
                <div class="col-6"><strong>Type:</strong> ${esc(i.type)}</div>
                <div class="col-6"><strong>Severity:</strong> <span class="severity-badge ${sev}">${esc(sev).charAt(0).toUpperCase() + esc(sev).slice(1)}</span></div>
                <div class="col-6"><strong>Status:</strong> <span class="status-badge ${st}">${esc(st).charAt(0).toUpperCase() + esc(st).slice(1)}</span></div>
                <div class="col-6"><strong>Date:</strong> ${new Date(i.created_at).toLocaleString()}</div>
                <div class="col-6"><strong>Reported By:</strong> ${esc(i.reporter_first)} ${esc(i.reporter_last)}</div>
                ${i.assignee_first ? `<div class="col-6"><strong>Assignee:</strong> ${esc(i.assignee_first)} ${esc(i.assignee_last)}</div>` : ''}
                ${i.lab_name ? `<div class="col-6"><strong>Lab:</strong> ${esc(i.lab_name)}</div>` : ''}
                ${i.station_code ? `<div class="col-6"><strong>Station:</strong> ${esc(i.station_code)}</div>` : ''}
                <div class="col-12"><strong>Description:</strong><p class="mt-1">${esc(i.description) || 'None'}</p></div>
                ${i.resolution_notes ? `<div class="col-12"><strong>Resolution Notes:</strong><p class="mt-1">${esc(i.resolution_notes)}</p></div>` : ''}
                ${i.resolved_at ? `<div class="col-6"><strong>Resolved At:</strong> ${new Date(i.resolved_at).toLocaleString()}</div>` : ''}
                ${logHtml}
            </div>`;
        } catch (e) {
            document.getElementById('view-body').innerHTML = '<p class="text-danger">Failed to load details.</p>';
        }
    }

    document.getElementById('incidentForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData);
        if (data.action === 'update') {
            if (data.assigned_to === '') data.assigned_to = null;
            if (data.lab_id === '') data.lab_id = null;
            if (data.station_id === '') data.station_id = null;
        } else {
            ['lab_id', 'station_id', 'assigned_to'].forEach(k => {
                if (data[k] === '') delete data[k];
            });
        }
        
        try {
            const response = await fetch('/api/incidents/create.php', {
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

    async function deleteIncident() {
        if (!confirm('Are you sure you want to delete this incident?')) return;
        
        const id = document.getElementById('incident-id').value;
        try {
            const response = await fetch('/api/incidents/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', incident_id: id })
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
