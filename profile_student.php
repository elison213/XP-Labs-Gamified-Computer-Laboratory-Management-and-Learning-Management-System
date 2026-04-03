<?php
/**
 * XPLabs - Student Profile Page
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::require();

$db = Database::getInstance();
$role = $_SESSION['user_role'];
$userId = Auth::id();

// Get user data
$user = $db->fetch(
    "SELECT u.*, 
            COALESCE(upb.total_earned, 0) as total_points,
            COALESCE(upb.balance, 0) as balance
     FROM users u
     LEFT JOIN user_point_balances upb ON u.id = upb.user_id
     WHERE u.id = ?",
    [$userId]
);

// Get user's achievements
$achievements = $db->fetchAll(
    "SELECT ua.*, a.name, a.icon, a.description, a.points_reward
     FROM user_achievements ua
     JOIN achievements a ON ua.achievement_id = a.id
     WHERE ua.user_id = ?
     ORDER BY ua.earned_at DESC",
    [$userId]
);

// Get recent activity
$recentQuizzes = $db->fetchAll(
    "SELECT qa.*, q.title as quiz_title, q.course_id
     FROM quiz_attempts qa
     JOIN quizzes q ON qa.quiz_id = q.id
     WHERE qa.user_id = ? AND qa.status = 'completed'
     ORDER BY qa.finished_at DESC
     LIMIT 5",
    [$userId]
);

$recentSubmissions = $db->fetchAll(
    "SELECT s.*, a.title as assignment_title, a.max_points
     FROM submissions s
     JOIN assignments a ON s.assignment_id = a.id
     WHERE s.user_id = ?
     ORDER BY s.submitted_at DESC
     LIMIT 5",
    [$userId]
);

// Handle profile update
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    try {
        $db->update('users', [
            'first_name' => $_POST['first_name'] ?? $user['first_name'],
            'last_name' => $_POST['last_name'] ?? $user['last_name'],
            'email' => $_POST['email'] ?? $user['email'],
        ], 'id = ?', [$userId]);
        
        // Update password if provided
        if (!empty($_POST['new_password'])) {
            $db->update('users', [
                'password_hash' => password_hash($_POST['new_password'], PASSWORD_DEFAULT),
            ], 'id = ?', [$userId]);
        }
        
        $message = ['type' => 'success', 'text' => 'Profile updated successfully'];
        $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
    } catch (\Exception $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-main: #0f172a; --bg-card: #1e293b; --border: #334155;
            --text: #e2e8f0; --text-muted: #94a3b8; --accent: #6366f1;
            --green: #22c55e; --yellow: #eab308; --red: #ef4444;
        }
        body { background: var(--bg-main); color: var(--text); font-family: 'Segoe UI', system-ui, sans-serif; min-height: 100vh; }
        .sidebar { position: fixed; top: 0; left: 0; width: 260px; height: 100vh; background: var(--bg-card); border-right: 1px solid var(--border); z-index: 1000; overflow-y: auto; }
        .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .sidebar-brand h4 { margin: 0; font-weight: 700; color: var(--text); }
        .sidebar-brand small { color: var(--text-muted); }
        .sidebar-nav { padding: 1rem 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem; color: var(--text-muted); text-decoration: none; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(99, 102, 241, 0.1); color: var(--accent); }
        .sidebar-nav a i { width: 20px; text-align: center; }
        .sidebar-nav .nav-section { padding: 0.5rem 1.5rem; font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted); margin-top: 0.5rem; }
        .main-content { margin-left: 260px; padding: 2rem; }
        .xp-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .xp-card .card-header { background: transparent; border-bottom: 1px solid var(--border); padding: 1rem 1.5rem; }
        .xp-card .card-header h5 { margin: 0; font-weight: 600; color: var(--text); }
        .xp-card .card-body { padding: 1.5rem; }
        .form-control, .form-select { background: var(--bg-main); border: 1px solid var(--border); color: var(--text); }
        .form-control:focus, .form-select:focus { border-color: var(--accent); box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25); }
        .form-label { color: var(--text-muted); font-size: 0.85rem; }
        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), #8b5cf6); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: #fff; margin: 0 auto 1rem; }
        .stat-box { background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; padding: 1rem; text-align: center; }
        .stat-box .value { font-size: 1.5rem; font-weight: 700; color: var(--text); }
        .stat-box .label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; }
        .achievement-icon { width: 50px; height: 50px; border-radius: 50%; background: var(--bg-main); border: 2px solid var(--accent); display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-right: 0.5rem; }
        .activity-item { padding: 0.75rem 0; border-bottom: 1px solid var(--border); }
        .activity-item:last-child { border-bottom: none; }
        .text-muted { color: var(--text-muted) !important; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/student_sidebar.php'; ?>

    <div class="main-content">
        <h2 class="mb-4"><i class="bi bi-person me-2"></i>My Profile</h2>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show"><?= e($message['text']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="xp-card text-center">
                    <div class="card-body">
                        <div class="profile-avatar"><?= strtoupper(substr($user['first_name'], 0, 1)) ?></div>
                        <h4 class="mb-1"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h4>
                        <p class="text-muted small mb-2">LRN: <?= e($user['lrn']) ?></p>
                        <p class="text-muted small mb-3"><?= e($user['email'] ?? 'No email set') ?></p>
                        <div class="row g-2">
                            <div class="col-6"><div class="stat-box"><div class="value text-primary"><?= number_format($user['total_points']) ?></div><div class="label">Points</div></div></div>
                            <div class="col-6"><div class="stat-box"><div class="value"><?= number_format($user['balance']) ?></div><div class="label">Balance</div></div></div>
                        </div>
                    </div>
                </div>
                <?php if (!empty($achievements)): ?>
                <div class="xp-card mt-4">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-award me-2"></i>Achievements</h5></div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap">
                            <?php foreach ($achievements as $ach): ?>
                            <div class="achievement-icon" title="<?= e($ach['name'] . ': ' . $ach['description']) ?>"><?= $ach['icon'] ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-md-8">
                <div class="xp-card mb-4">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Edit Profile</h5></div>
                    <div class="card-body">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" value="<?= e($user['first_name']) ?>" required></div>
                                <div class="col-md-6 mb-3"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" value="<?= e($user['last_name']) ?>" required></div>
                            </div>
                            <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($user['email'] ?? '') ?>"></div>
                            <div class="mb-3"><label class="form-label">New Password (leave blank to keep current)</label><input type="password" name="new_password" class="form-control" placeholder="Enter new password..."></div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
                        </form>
                    </div>
                </div>

                <?php if (!empty($recentQuizzes)): ?>
                <div class="xp-card mb-4">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-journal-check me-2"></i>Recent Quizzes</h5></div>
                    <div class="card-body p-0">
                        <?php foreach ($recentQuizzes as $q): ?>
                        <div class="activity-item px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><div class="fw-semibold"><?= e($q['quiz_title']) ?></div><div class="text-muted small"><?= $q['finished_at'] ? date('M j, Y', strtotime($q['finished_at'])) : '' ?></div></div>
                                <div class="text-end"><div class="fw-bold"><?= $q['total_questions'] > 0 ? number_format(($q['correct_answers'] / $q['total_questions']) * 100, 1) : 0 ?>%</div><div class="text-muted small"><?= $q['correct_answers'] ?>/<?= $q['total_questions'] ?> correct</div></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($recentSubmissions)): ?>
                <div class="xp-card">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-upload me-2"></i>Recent Submissions</h5></div>
                    <div class="card-body p-0">
                        <?php foreach ($recentSubmissions as $s): ?>
                        <div class="activity-item px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><div class="fw-semibold"><?= e($s['assignment_title']) ?></div><div class="text-muted small"><?= $s['submitted_at'] ? date('M j, Y', strtotime($s['submitted_at'])) : '' ?></div></div>
                                <div class="text-end">
                                    <?php if ($s['status'] === 'graded'): ?><span class="badge bg-success"><?= number_format($s['grade'], 1) ?>/<?= $s['max_points'] ?></span>
                                    <?php elseif ($s['is_late']): ?><span class="badge bg-warning text-dark">Late</span>
                                    <?php else: ?><span class="badge bg-primary">Submitted</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
