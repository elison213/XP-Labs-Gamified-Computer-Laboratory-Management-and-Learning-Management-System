<?php
/**
 * XPLabs - Leaderboard Page
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::require();

$db = Database::getInstance();
$role = $_SESSION['user_role'];
$userId = Auth::id();

// Get leaderboard from user_point_balances
$leaderboard = $db->fetchAll(
    "SELECT u.id, u.lrn, u.first_name, u.last_name,
            COALESCE(upb.total_earned, 0) as total_points,
            COALESCE(upb.balance, 0) as balance
     FROM users u
     LEFT JOIN user_point_balances upb ON u.id = upb.user_id
     WHERE u.role = 'student'
     ORDER BY total_points DESC, u.first_name ASC
     LIMIT 50"
);

// Get current user's rank
$userRank = $db->fetch(
    "SELECT rank FROM (
         SELECT u2.id, 
                @row_num := @row_num + 1 as rank
         FROM users u2
         LEFT JOIN user_point_balances upb2 ON u2.id = upb2.user_id,
         (SELECT @row_num := 0) r
         WHERE u2.role = 'student'
         ORDER BY COALESCE(upb2.total_earned, 0) DESC
       ) ranked WHERE id = ?",
    [$userId]
);
$userRank = $userRank ? $userRank['rank'] : '-';

// Get user's achievements
$userAchievements = $db->fetchAll(
    "SELECT ua.*, a.name, a.icon, a.description
     FROM user_achievements ua
     JOIN achievements a ON ua.achievement_id = a.id
     WHERE ua.user_id = ?
     ORDER BY ua.earned_at DESC
     LIMIT 5",
    [$userId]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-main: #0f172a;
            --bg-card: #1e293b;
            --border: #334155;
            --text: #e2e8f0;
            --text-muted: #94a3b8;
            --accent: #6366f1;
            --gold: #fbbf24;
            --silver: #94a3b8;
            --bronze: #d97706;
        }
        
        body {
            background: var(--bg-main);
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

        /* Podium */
        .podium {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .podium-place {
            text-align: center;
            border-radius: 12px;
            padding: 1.5rem 1rem;
            min-width: 140px;
            transition: transform 0.2s;
        }
        .podium-place:hover { transform: translateY(-4px); }
        .podium-place.first {
            background: linear-gradient(135deg, #92400e, #b45309);
            order: 2;
            padding-top: 2rem;
        }
        .podium-place.second {
            background: linear-gradient(135deg, #475569, #64748b);
            order: 1;
        }
        .podium-place.third {
            background: linear-gradient(135deg, #78350f, #92400e);
            order: 3;
        }
        .podium-rank { font-size: 2rem; margin-bottom: 0.5rem; }
        .podium-name { font-weight: 600; color: var(--text); margin-bottom: 0.25rem; }
        .podium-points { font-size: 1.25rem; font-weight: 700; color: var(--text); }
        .podium-avatar {
            width: 60px; height: 60px; border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.3);
            margin: 0 auto 0.75rem;
            background: var(--bg-main);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
        }

        /* Leaderboard table */
        .xp-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 12px; overflow: hidden;
        }
        .xp-card .card-header {
            background: transparent; border-bottom: 1px solid var(--border);
            padding: 1rem 1.5rem;
        }
        .xp-card .card-header h5 { margin: 0; font-weight: 600; color: var(--text); }
        .xp-card .card-body { padding: 0; }

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
        .xp-table tr:hover { background: rgba(99, 102, 241, 0.1); }
        .xp-table tr.highlight { background: rgba(99, 102, 241, 0.2); }

        .rank-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; border-radius: 50%;
            font-weight: 700; font-size: 0.8rem;
        }
        .rank-badge.gold { background: var(--gold); color: #000; }
        .rank-badge.silver { background: var(--silver); color: #000; }
        .rank-badge.bronze { background: var(--bronze); color: var(--text); }
        .rank-badge.normal { background: var(--bg-main); color: var(--text-muted); border: 1px solid var(--border); }

        .stat-pill {
            display: inline-block; padding: 0.25rem 0.5rem;
            border-radius: 4px; font-size: 0.7rem;
            background: var(--bg-main); border: 1px solid var(--border);
            margin-right: 0.25rem;
        }

        .achievement-icon {
            width: 40px; height: 40px; border-radius: 50%;
            background: var(--bg-main); border: 2px solid var(--accent);
            display: inline-flex; align-items: center; justify-content: center;
            margin-right: 0.5rem; font-size: 1.2rem;
        }
        .text-muted { color: var(--text-muted) !important; }
    </style>
</head>
<body>
    <?php
    // Include the appropriate sidebar based on the user's role.
    if ($role === 'student') {
        include __DIR__ . '/components/student_sidebar.php';
    } else {
        include __DIR__ . '/components/admin_sidebar.php';
    }
    ?>
<div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-trophy me-2" style="color: var(--gold)"></i>Leaderboard</h2>
                <p class="text-muted mb-0">Top performers this semester</p>
            </div>
            <?php if ($role === 'student'): ?>
            <div class="stat-pill">
                <i class="bi bi-hash me-1"></i>Your Rank: #<?= $userRank ?? '-' ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (count($leaderboard) >= 3): ?>
        <!-- Podium -->
        <div class="podium">
            <?php
            $second = $leaderboard[1];
            $first = $leaderboard[0];
            $third = $leaderboard[2];
            ?>
            <div class="podium-place second">
                <div class="podium-avatar"><?= strtoupper(substr($second['first_name'], 0, 1)) ?></div>
                <div class="podium-rank">🥈</div>
                <div class="podium-name"><?= e($second['first_name'] . ' ' . $second['last_name']) ?></div>
                <div class="podium-points"><?= number_format($second['total_points']) ?> pts</div>
            </div>
            <div class="podium-place first">
                <div class="podium-avatar"><?= strtoupper(substr($first['first_name'], 0, 1)) ?></div>
                <div class="podium-rank">🥇</div>
                <div class="podium-name"><?= e($first['first_name'] . ' ' . $first['last_name']) ?></div>
                <div class="podium-points"><?= number_format($first['total_points']) ?> pts</div>
            </div>
            <div class="podium-place third">
                <div class="podium-avatar"><?= strtoupper(substr($third['first_name'], 0, 1)) ?></div>
                <div class="podium-rank">🥉</div>
                <div class="podium-name"><?= e($third['first_name'] . ' ' . $third['last_name']) ?></div>
                <div class="podium-points"><?= number_format($third['total_points']) ?> pts</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'student' && !empty($userAchievements)): ?>
        <!-- Recent Achievements -->
        <div class="xp-card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-award me-2"></i>Your Recent Achievements</h5>
            </div>
            <div class="card-body p-3">
                <div class="d-flex flex-wrap align-items-center">
                    <?php foreach ($userAchievements as $ach): ?>
                    <div class="achievement-icon" title="<?= e($ach['name'] . ': ' . $ach['description']) ?>">
                        <?= $ach['icon'] ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Full Ranking Table -->
        <div class="xp-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Full Rankings</h5>
                <span class="text-muted small"><?= count($leaderboard) ?> students</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="xp-table">
                        <thead>
<tr>
    <th style="width: 60px">Rank</th>
    <th>Student</th>
    <th class="text-center">Total Points</th>
    <th class="text-center">Balance</th>
</tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaderboard as $i => $user): 
                                $rank = $i + 1;
                                $isCurrentUser = $user['id'] == $userId;
                                $badgeClass = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : 'normal'));
                            ?>
                            <tr class="<?= $isCurrentUser ? 'highlight' : '' ?>">
                                <td><span class="rank-badge <?= $badgeClass ?>"><?= $rank ?></span></td>
                                <td>
                                    <?= e($user['first_name'] . ' ' . $user['last_name']) ?>
                                    <?php if ($isCurrentUser): ?>
                                    <span class="badge bg-primary ms-1">You</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold"><?= number_format($user['total_points']) ?></td>
                                <td class="text-center"><?= number_format($user['balance']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($leaderboard)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    No rankings available yet
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
