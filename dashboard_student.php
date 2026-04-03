<?php
/**
 * XPLabs - Student Dashboard (Gamified)
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Services\PointService;

Auth::requireRole('student');

$userId = Auth::id();
$db = Database::getInstance();
$pointService = new PointService();

// Get user info
$user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);

// Points
$points = $pointService->getBalance($userId);
$totalEarned = $pointService->getTotalEarned($userId);
$rank = $pointService->getUserRank($userId);

// Achievements
$achievements = $db->fetchAll(
    "SELECT a.*, ua.earned_at
     FROM user_achievements ua
     JOIN achievements a ON ua.achievement_id = a.id
     WHERE ua.user_id = ?
     ORDER BY ua.earned_at DESC",
    [$userId]
);

// Today's attendance
$todayAttendance = $db->fetch(
    "SELECT * FROM attendance_sessions 
     WHERE user_id = ? AND DATE(clock_in) = CURDATE() 
     ORDER BY clock_in DESC LIMIT 1",
    [$userId]
);

// Recent points history
$recentPoints = $db->fetchAll(
    "SELECT * FROM user_points 
     WHERE user_id = ? 
     ORDER BY created_at DESC 
     LIMIT 10",
    [$userId]
);

// Active assignments
$activeAssignments = $db->fetchAll(
    "SELECT a.*, c.name as course_name 
     FROM assignments a
     JOIN courses c ON a.course_id = c.id
     WHERE a.due_date >= CURDATE()
     ORDER BY a.due_date ASC
     LIMIT 5"
);

// Upcoming quizzes
$upcomingQuizzes = $db->fetchAll(
    "SELECT q.*, c.name as course_name 
     FROM quizzes q
     JOIN courses c ON q.course_id = c.id
     WHERE q.status IN ('active', 'scheduled')
     ORDER BY q.created_at DESC
     LIMIT 5"
);

// Leaderboard position
$leaderboardPos = $db->fetchOne(
    "SELECT pos FROM (
        SELECT user_id, @row_num := @row_num + 1 AS pos
        FROM (
            SELECT user_id, SUM(points) as total
            FROM user_points
            GROUP BY user_id
            ORDER BY total DESC
        ) ranked, (SELECT @row_num := 0) r
    ) user_rank WHERE user_id = ?",
    [$userId]
) ?? '-';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - XPLabs</title>
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
            --pink: #ec4899;
            --cyan: #06b6d4;
        }
        
        body {
            background: var(--bg-dark);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }
        <?php if (($_SESSION['user_role'] ?? 'admin') === 'teacher'): ?>
        body { background: #f1f5f9 !important; }
        .main-content { background: #f1f5f9; }
        .stat-card { background: #fff; border-color: #e2e8f0; }
        .stat-card .value { color: #1e293b; }
        .stat-card .label { color: #64748b; }
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
        .page-header h2 { color: #1e293b; }
        .page-header p { color: #64748b; }
        .sidebar { background: #fff; border-color: #e2e8f0; }
        .sidebar-brand h4 { color: #1e293b; }
        .sidebar-brand small { color: #64748b; }
        .sidebar-nav a { color: #64748b; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(99, 102, 241, 0.1); color: #6366f1; }
        .sidebar-nav .nav-section { color: #64748b; }
        .quick-action { background: #fff; border-color: #e2e8f0; color: #1e293b; }
        .quick-action:hover { border-color: #6366f1; background: rgba(99, 102, 241, 0.05); color: #1e293b; }
        .student-name { color: #1e293b; }
        .student-detail { color: #64748b; }
        .activity-content strong { color: #1e293b; }
        .activity-time { color: #64748b; }
        .activity-item { border-color: #e2e8f0; }
        .leaderboard-item { border-color: #e2e8f0; }
        .rank.default { background: #f1f5f9; color: #64748b; }
        <?php endif; ?>

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

        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, var(--accent), #8b5cf6);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            border: 3px solid rgba(255,255,255,0.3);
        }
        .profile-name { font-size: 1.5rem; font-weight: 700; color: #fff; }
        .profile-detail { color: rgba(255,255,255,0.8); font-size: 0.9rem; }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
        }
        .stat-box .value {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
        }
        .stat-box .label {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .stat-box.accent .value { color: var(--accent); }
        .stat-box.green .value { color: var(--green); }
        .stat-box.yellow .value { color: var(--yellow); }
        .stat-box.pink .value { color: var(--pink); }

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

        /* Achievement Badges */
        .achievement-badge {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            background: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        .achievement-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .achievement-name { font-weight: 600; color: #fff; }
        .achievement-desc { font-size: 0.75rem; color: var(--text-muted); }

        /* Points History */
        .point-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        .point-item:last-child { border-bottom: none; }
        .point-amount {
            font-weight: 700;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .point-amount.positive { background: rgba(34, 197, 94, 0.1); color: var(--green); }
        .point-desc { flex: 1; margin-left: 1rem; }
        .point-time { font-size: 0.75rem; color: var(--text-muted); }

        /* Assignment Card */
        .assignment-item {
            padding: 1rem;
            background: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 0.75rem;
        }
        .assignment-title { font-weight: 600; color: #fff; }
        .assignment-meta { font-size: 0.8rem; color: var(--text-muted); }
        .due-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .due-badge.urgent { background: rgba(239, 68, 68, 0.1); color: var(--red); }
        .due-badge.soon { background: rgba(234, 179, 8, 0.1); color: var(--yellow); }
        .due-badge.normal { background: rgba(34, 197, 94, 0.1); color: var(--green); }

        /* Attendance Status */
        .attendance-status {
            padding: 1.5rem;
            background: var(--bg-dark);
            border-radius: 12px;
            text-align: center;
        }
        .attendance-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-brand">
            <h4><i class="bi bi-flask me-2"></i>XPLabs</h4>
            <small>Student Portal</small>
        </div>
        <div class="sidebar-nav">
            <a href="dashboard_student.php" class="active"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            <a href="leaderboard.php"><i class="bi bi-trophy"></i> Leaderboard</a>
            <a href="profile_student.php"><i class="bi bi-person"></i> My Profile</a>
            
            <div class="nav-section">Learning</div>
            <a href="assignments.php"><i class="bi bi-journal-text"></i> Assignments</a>
            <a href="submissions.php"><i class="bi bi-upload"></i> My Submissions</a>
            
            <div class="nav-section">Lab</div>
            <a href="lab_seatplan.php"><i class="bi bi-layout-text-window-reverse"></i> Seat Plan</a>
            <a href="attendance_history.php"><i class="bi bi-calendar-check"></i> Attendance</a>
            
            <div class="nav-section mt-4">Account</div>
            <a href="api/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="d-flex align-items-center gap-3">
                <div class="profile-avatar">
                    <?= strtoupper(substr($user['first_name'] ?? 'S', 0, 1)) ?>
                </div>
                <div>
                    <div class="profile-name"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></div>
                    <div class="profile-detail">
                        <?= e($user['grade_level'] ?? '') ?> - <?= e($user['section'] ?? '') ?> | LRN: <?= e($user['lrn']) ?>
                    </div>
                </div>
                <div class="ms-auto text-end">
                    <div class="profile-detail">Current Rank</div>
                    <div class="profile-name">#<?= e($leaderboardPos) ?></div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-box accent">
                <div class="value"><?= number_format($points) ?></div>
                <div class="label">Total Points</div>
            </div>
            <div class="stat-box green">
                <div class="value"><?= count($achievements) ?></div>
                <div class="label">Achievements</div>
            </div>
            <div class="stat-box yellow">
                <div class="value"><?= $todayAttendance ? '✓' : '—' ?></div>
                <div class="label">Today's Attendance</div>
            </div>
            <div class="stat-box pink">
                <div class="value"><?= count($activeAssignments) ?></div>
                <div class="label">Pending Tasks</div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Main Column -->
            <div class="col-lg-8">
                <!-- Attendance Status -->
                <div class="xp-card mb-3">
                    <div class="card-header">
                        <h5><i class="bi bi-calendar-check me-2"></i>Today's Attendance</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($todayAttendance): ?>
                        <div class="attendance-status">
                            <div class="attendance-icon text-success">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            <h5 class="mb-1" style="color: var(--text)">You're checked in!</h5>
                            <p class="text-muted mb-0">Clock-in: <?= date('g:i A', strtotime($todayAttendance['clock_in'])) ?></p>
                            <?php if ($todayAttendance['clock_out']): ?>
                            <p class="text-muted mb-0">Clock-out: <?= date('g:i A', strtotime($todayAttendance['clock_out'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="attendance-status">
                            <div class="attendance-icon text-warning">
                                <i class="bi bi-clock-fill"></i>
                            </div>
                            <h5 class="mb-1" style="color: var(--text)">Not checked in yet</h5>
                            <p class="text-muted mb-2">Scan your QR code at the kiosk to clock in</p>
                            <a href="qr_scan.php" class="btn btn-primary">
                                <i class="bi bi-qr-code me-1"></i> Go to Kiosk
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Active Assignments -->
                <div class="xp-card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="bi bi-journal-text me-2"></i>Active Assignments</h5>
                        <a href="assignments.php" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php foreach ($activeAssignments as $assignment): 
                            $daysLeft = floor((strtotime($assignment['due_date']) - time()) / 86400);
                            $dueClass = $daysLeft <= 1 ? 'urgent' : ($daysLeft <= 3 ? 'soon' : 'normal');
                        ?>
                        <div class="assignment-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="assignment-title"><?= e($assignment['title']) ?></div>
                                    <div class="assignment-meta"><?= e($assignment['course_name']) ?></div>
                                </div>
                                <span class="due-badge <?= $dueClass ?>">
                                    <?= $daysLeft <= 0 ? 'Due Today' : $daysLeft . 'd left' ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($activeAssignments)): ?>
                        <div class="text-center text-muted py-3">No active assignments</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Points -->
                <div class="xp-card">
                    <div class="card-header">
                        <h5><i class="bi bi-star me-2"></i>Points History</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recentPoints as $point): ?>
                        <div class="point-item">
                            <div class="point-amount positive">+<?= $point['points'] ?></div>
                            <div class="point-desc"><?= e($point['reason']) ?></div>
                            <div class="point-time"><?= date('M j', strtotime($point['created_at'])) ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($recentPoints)): ?>
                        <div class="text-center text-muted py-3">No points earned yet. Start attending classes and completing assignments!</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Achievements -->
                <div class="xp-card mb-3">
                    <div class="card-header">
                        <h5><i class="bi bi-award me-2"></i>Achievements</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach (array_slice($achievements, 0, 5) as $achievement): ?>
                        <div class="achievement-badge">
                            <div class="achievement-icon" style="background: rgba(234, 179, 8, 0.1); color: var(--yellow);">
                                <i class="bi bi-<?= $achievement['icon'] ?? 'star' ?>"></i>
                            </div>
                            <div>
                                <div class="achievement-name"><?= e($achievement['name']) ?></div>
                                <div class="achievement-desc"><?= e($achievement['description'] ?? '') ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($achievements)): ?>
                        <div class="text-center text-muted py-3">No achievements yet. Keep earning points!</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="xp-card">
                    <div class="card-header">
                        <h5><i class="bi bi-lightning me-2"></i>Quick Links</h5>
                    </div>
                    <div class="card-body">
                        <a href="leaderboard.php" class="quick-action d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2" style="background: var(--bg-dark); border: 1px solid var(--border); color: var(--text);">
                            <i class="bi bi-trophy text-warning fs-4"></i>
                            <div>
                                <div class="fw-semibold" style="color: var(--text)">Leaderboard</div>
                                <small class="text-muted">See your ranking</small>
                            </div>
                        </a>
                        <a href="qr_scan.php" class="quick-action d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2" style="background: var(--bg-dark); border: 1px solid var(--border); color: var(--text);">
                            <i class="bi bi-qr-code text-success fs-4"></i>
                            <div>
                                <div class="fw-semibold" style="color: var(--text)">QR Check-In</div>
                                <small class="text-muted">Clock in to lab</small>
                            </div>
                        </a>
                        <a href="profile_student.php" class="quick-action d-flex align-items-center gap-3 p-3 rounded text-decoration-none" style="background: var(--bg-dark); border: 1px solid var(--border); color: var(--text);">
                            <i class="bi bi-person text-info fs-4"></i>
                            <div>
                                <div class="fw-semibold" style="color: var(--text)">My Profile</div>
                                <small class="text-muted">View details</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>