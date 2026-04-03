<?php
/**
 * XPLabs - Announcements Page
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::require();

$db = Database::getInstance();
$role = $_SESSION['user_role'];
$userId = Auth::id();

// Handle POST for creating announcement
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $announcementId = $db->insert('announcements', [
                'title' => $_POST['title'],
                'content' => $_POST['content'] ?? '',
                'created_by' => $userId,
                'target_audience' => $_POST['target_audience'] ?? 'all',
                'is_pinned' => !empty($_POST['is_pinned']) ? 1 : 0,
                'is_active' => 1,
                'expires_at' => $_POST['expires_at'] ?: null,
            ]);
            $message = ['type' => 'success', 'text' => 'Announcement created successfully'];
        } elseif ($action === 'delete' && in_array($role, ['admin', 'teacher'])) {
            $db->update('announcements', ['is_active' => 0], 'id = ?', [(int) $_POST['announcement_id']]);
            $message = ['type' => 'success', 'text' => 'Announcement deleted'];
        }
    } catch (\Exception $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}

// Get announcements
if ($role === 'admin') {
    $announcements = $db->fetchAll(
        "SELECT a.*, u.first_name, u.last_name
         FROM announcements a
         LEFT JOIN users u ON a.created_by = u.id
         WHERE a.is_active = 1
         ORDER BY a.is_pinned DESC, a.created_at DESC"
    );
} elseif ($role === 'teacher') {
    $announcements = $db->fetchAll(
        "SELECT a.*, u.first_name, u.last_name
         FROM announcements a
         LEFT JOIN users u ON a.created_by = u.id
         WHERE a.is_active = 1 AND (a.created_by = ? OR a.target_audience = 'all')
         ORDER BY a.is_pinned DESC, a.created_at DESC",
        [$userId]
    );
} else {
    $announcements = $db->fetchAll(
        "SELECT a.*, u.first_name, u.last_name
         FROM announcements a
         LEFT JOIN users u ON a.created_by = u.id
         WHERE a.is_active = 1 
           AND (a.target_audience = 'all' OR a.target_audience = 'students')
         ORDER BY a.is_pinned DESC, a.created_at DESC"
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - XPLabs</title>
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

        .announcement-card {
            background: var(--bg-main); border: 1px solid var(--border);
            border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem;
            transition: all 0.2s;
        }
        .announcement-card:hover { border-color: var(--accent); }
        .announcement-card.pinned { border-left: 4px solid var(--accent); }
        .announcement-card .title { font-weight: 600; color: var(--text); margin-bottom: 0.5rem; }
        .announcement-card .content { color: var(--text-muted); font-size: 0.9rem; white-space: pre-wrap; }
        .announcement-card .meta { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.75rem; }

        .form-control, .form-select {
            background: var(--bg-main); border: 1px solid var(--border); color: var(--text);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent); box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }
        .form-label { color: var(--text-muted); font-size: 0.85rem; }
    </style>
</head>
<body>
    <?php 
    if ($role === 'student') {
        include __DIR__ . '/components/student_sidebar.php';
    } else {
        include __DIR__ . '/components/admin_sidebar.php';
    }
    ?>
<div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-megaphone me-2"></i>Announcements</h2>
                <p class="text-muted mb-0">Stay updated with the latest news and updates</p>
            </div>
            <?php if (in_array($role, ['admin', 'teacher'])): ?>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreate">
                <i class="bi bi-plus-lg me-1"></i> New Announcement
            </button>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show">
            <?= e($message['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (empty($announcements)): ?>
        <div class="xp-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-megaphone fs-1 text-muted d-block mb-3"></i>
                <h4>No Announcements Yet</h4>
                <p class="text-muted">Check back later for updates</p>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($announcements as $a): ?>
        <div class="announcement-card <?= $a['is_pinned'] ? 'pinned' : '' ?>">
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <div class="title">
                        <?php if ($a['is_pinned']): ?>
                        <i class="bi bi-pin-fill text-primary me-1"></i>
                        <?php endif; ?>
                        <?= e($a['title']) ?>
                    </div>
                    <div class="content"><?= nl2br(e($a['content'])) ?></div>
                    <div class="meta">
                        <i class="bi bi-person me-1"></i><?= e(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? 'System')) ?>
                        <span class="mx-2">•</span>
                        <i class="bi bi-clock me-1"></i><?= date('M j, Y g:i A', strtotime($a['created_at'])) ?>
                        <?php if ($a['expires_at']): ?>
                        <span class="mx-2">•</span>
                        <i class="bi bi-calendar-x me-1"></i>Expires: <?= date('M j, Y', strtotime($a['expires_at'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (in_array($role, ['admin', 'teacher']) && ($role === 'admin' || $a['created_by'] == $userId)): ?>
                <form method="POST" class="ms-2" onsubmit="return confirm('Delete this announcement?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="announcement_id" value="<?= $a['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Create Modal -->
    <?php if (in_array($role, ['admin', 'teacher'])): ?>
    <div class="modal fade" id="modalCreate" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content *</label>
                        <textarea name="content" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target Audience</label>
                        <select name="target_audience" class="form-select">
                            <option value="all">Everyone</option>
                            <option value="students">Students Only</option>
                            <option value="teachers">Teachers Only</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expires At</label>
                        <input type="datetime-local" name="expires_at" class="form-control">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_pinned" class="form-check-input" id="pinCheck">
                        <label class="form-check-label" for="pinCheck">Pin to top</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Publish</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
