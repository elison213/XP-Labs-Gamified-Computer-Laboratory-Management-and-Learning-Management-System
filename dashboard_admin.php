<?php
/**
 * XPLabs - Admin Dashboard
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Services\LabService;
use XPLabs\Services\UserService;

Auth::requireRole('admin');

$db = Database::getInstance();
$labService = new LabService();
$userService = new UserService();

// Stats
$labStats = $labService->getStats();
$totalStudents = (int) $db->fetchOne("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1");
$totalTeachers = (int) $db->fetchOne("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND is_active = 1");
$activeSessions = (int) $db->fetchOne("SELECT COUNT(*) FROM attendance_sessions WHERE status = 'active'");
$todayAttendance = (int) $db->fetchOne("SELECT COUNT(DISTINCT user_id) FROM attendance_sessions WHERE DATE(clock_in) = CURDATE()");

// Recent activity
$recentActivity = $db->fetchAll(
    "SELECT al.*, u.first_name, u.last_name, u.role 
     FROM admin_logs al 
     LEFT JOIN users u ON al.user_id = u.id 
     ORDER BY al.created_at DESC 
     LIMIT 10"
);

// Top students this week
$topStudents = $db->fetchAll(
    "SELECT u.id, u.first_name, u.last_name, u.lrn, u.grade_level, u.section,
            COALESCE(SUB.total_points, 0) as total_points
     FROM users u
     LEFT JOIN (
         SELECT user_id, SUM(points) as total_points 
         FROM user_points 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY user_id
     ) SUB ON u.id = SUB.user_id
     WHERE u.role = 'student' AND u.is_active = 1
     ORDER BY total_points DESC
     LIMIT 5"
);

// Floors
$floors = $labService->getFloors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --bg-panel: #1e293b;
            --border: #334155;
            --text: #e2e8f0;
            --text-muted: #94a3b8;
            --accent: #6366f1;
            --green: #22c55e;
            --yellow: #eab308;
            --red: #ef4444;
            --gray: #64748b;
        }
        
        body {
            background: var(--bg-dark);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: var(--bg-card);
            border-right: 1px solid var(--border);
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        .sidebar-brand h4 { margin: 0; font-weight: 700; color: #fff; }
        .sidebar-brand small { color: var(--text-muted); }
        .sidebar-nav { padding: 1rem 0; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.2s;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent);
        }
        .sidebar-nav a i { width: 20px; text-align: center; }
        .sidebar-nav .nav-section {
            padding: 0.5rem 1.5rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-top: 0.5rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 2rem;
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .page-header h2 { margin: 0; font-weight: 700; color: #fff; }
        .page-header p { margin: 0.25rem 0 0; color: var(--text-muted); }

        /* Stat Cards */
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.2s;
        }
        .stat-card:hover { border-color: var(--accent); transform: translateY(-2px); }
        .stat-card .icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .stat-card .value { font-size: 1.75rem; font-weight: 700; color: #fff; }
        .stat-card .label { font-size: 0.85rem; color: var(--text-muted); }

        /* Cards */
        .xp-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }
        .xp-card .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border);
            padding: 1rem 1.5rem;
        }
        .xp-card .card-header h5 { margin: 0; font-weight: 600; color: #fff; }
        .xp-card .card-body { padding: 1.5rem; }

        /* Activity List */
        .activity-item {
            display: flex;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .activity-content { flex: 1; }
        .activity-content strong { color: #fff; }
        .activity-time { font-size: 0.75rem; color: var(--text-muted); }

        /* Leaderboard */
        .leaderboard-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        .leaderboard-item:last-child { border-bottom: none; }
        .rank {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }
        .rank.gold { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #000; }
        .rank.silver { background: linear-gradient(135deg, #94a3b8, #64748b); color: #fff; }
        .rank.bronze { background: linear-gradient(135deg, #d97706, #b45309); color: #fff; }
        .rank.default { background: var(--bg-dark); color: var(--text-muted); }
        .student-info { flex: 1; }
        .student-name { font-weight: 600; color: #fff; }
        .student-detail { font-size: 0.75rem; color: var(--text-muted); }
        .student-points { font-weight: 700; color: var(--accent); }

        /* Quick Actions */
        .quick-action {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text);
            transition: all 0.2s;
            margin-bottom: 0.75rem;
        }
        .quick-action:hover { border-color: var(--accent); background: rgba(99, 102, 241, 0.05); color: #fff; }
        .quick-action i { font-size: 1.25rem; color: var(--accent); }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include __DIR__ . '/components/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>Welcome, <?= e($_SESSION['user_first_name'] ?? 'Admin') ?>!</h2>
                <p>Here's what's happening in your lab today.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="monitoring.php" class="btn btn-primary">
                    <i class="bi bi-display me-1"></i> Open Lab Monitor
                </a>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon" style="background: rgba(34, 197, 94, 0.1); color: var(--green);">
                            <i class="bi bi-pc-display"></i>
                        </div>
                        <div>
                            <div class="value"><?= $labStats['active'] ?>/<?= $labStats['total'] ?></div>
                            <div class="label">Stations Active</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon" style="background: rgba(99, 102, 241, 0.1); color: var(--accent);">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <div class="value"><?= $totalStudents ?></div>
                            <div class="label">Total Students</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon" style="background: rgba(234, 179, 8, 0.1); color: var(--yellow);">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div>
                            <div class="value"><?= $todayAttendance ?></div>
                            <div class="label">Present Today</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon" style="background: rgba(239, 68, 68, 0.1); color: var(--red);">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <div>
                            <div class="value"><?= $totalTeachers ?></div>
                            <div class="label">Teachers</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Lab Overview -->
            <div class="col-lg-8">
                <!-- Lab Floors -->
                <div class="xp-card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="bi bi-building me-2"></i>Lab Floors</h5>
                        <a href="admin_system.php" class="btn btn-sm btn-outline-light">Manage</a>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($floors as $floor): 
                                $floorStats = $labService->getStats($floor['id']);
                            ?>
                            <div class="col-md-6">
                                <div class="p-3 rounded" style="background: var(--bg-dark); border: 1px solid var(--border);">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <strong class="text-white"><?= e($floor['name']) ?></strong>
                                        <span class="badge bg-primary"><?= $floorStats['total'] ?> stations</span>
                                    </div>
                                    <div class="d-flex gap-2 small">
                                        <span class="text-success"><?= $floorStats['active'] ?> active</span>
                                        <span class="text-warning"><?= $floorStats['idle'] ?> idle</span>
                                        <span class="text-secondary"><?= $floorStats['offline'] ?> offline</span>
                                    </div>
                                    <a href="monitoring.php?floor=<?= $floor['id'] ?>" class="btn btn-sm btn-outline-primary mt-2 w-100">
                                        <i class="bi bi-eye me-1"></i> View Floor
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($floors)): ?>
                            <div class="col-12 text-center text-muted py-4">
                                <i class="bi bi-building fs-1 d-block mb-2"></i>
                                <p>No lab floors configured yet</p>
                                <a href="admin_system.php" class="btn btn-primary">Add Lab Floor</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="xp-card">
                    <div class="card-header">
                        <h5><i class="bi bi-activity me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach (array_slice($recentActivity, 0, 8) as $activity): ?>
                        <div class="activity-item px-3">
                            <div class="activity-icon" style="background: rgba(99, 102, 241, 0.1); color: var(--accent);">
                                <i class="bi bi-<?= $activity['action'] === 'login' ? 'box-arrow-in-right' : ($activity['action'] === 'settings_change' ? 'gear' : 'circle') ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div><strong><?= e($activity['first_name'] . ' ' . $activity['last_name']) ?></strong> - <?= e($activity['action']) ?></div>
                                <div class="activity-time"><?= e($activity['details'] ?? '') ?></div>
                            </div>
                            <div class="activity-time"><?= date('M j, g:i A', strtotime($activity['created_at'])) ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($recentActivity)): ?>
                        <div class="text-center text-muted py-4">No recent activity</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="xp-card mb-3">
                    <div class="card-header">
                        <h5><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <a href="admin_users.php?action=import" class="quick-action">
                            <i class="bi bi-file-earmark-arrow-up"></i>
                            <div>
                                <div class="fw-semibold">Import Students</div>
                                <small class="text-muted">Bulk upload via CSV/Excel</small>
                            </div>
                        </a>
                        <a href="admin_system.php" class="quick-action">
                            <i class="bi bi-pc-display"></i>
                            <div>
                                <div class="fw-semibold">Manage Lab</div>
                                <small class="text-muted">Add floors & stations</small>
                            </div>
                        </a>
                        <a href="announcements.php" class="quick-action">
                            <i class="bi bi-megaphone"></i>
                            <div>
                                <div class="fw-semibold">Announcements</div>
                                <small class="text-muted">Post lab updates</small>
                            </div>
                        </a>
                        <a href="leaderboard.php" class="quick-action">
                            <i class="bi bi-trophy"></i>
                            <div>
                                <div class="fw-semibold">Leaderboard</div>
                                <small class="text-muted">View rankings</small>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Top Students -->
                <div class="xp-card">
                    <div class="card-header">
                        <h5><i class="bi bi-trophy me-2"></i>Top Students (This Week)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($topStudents as $i => $student): 
                            $rank = $i + 1;
                            $rankClass = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : 'default'));
                        ?>
                        <div class="leaderboard-item px-3">
                            <div class="rank <?= $rankClass ?>"><?= $rank ?></div>
                            <div class="student-info">
                                <div class="student-name"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></div>
                                <div class="student-detail"><?= e($student['grade_level'] ?? '') ?> - <?= e($student['section'] ?? '') ?></div>
                            </div>
                            <div class="student-points"><?= number_format($student['total_points']) ?> pts</div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($topStudents)): ?>
                        <div class="text-center text-muted py-4">No points recorded this week</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>