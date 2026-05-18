<?php
/**
 * XPLabs - Role-Aware Sidebar Component
 * Usage: Include this file after Auth is initialized
 * Teachers see limited navigation, Admins see full navigation
 */
// Determine the current user's role. Fallback to 'admin' if not set.
$currentRole = $_SESSION['user_role'] ?? 'admin';
$currentPage = basename($_SERVER['PHP_SELF']);
// Identify if the user is an admin. This will be used to conditionally display admin-only sections.
$isAdmin = ($currentRole === 'admin');
?>
<style>
.sidebar {
    position: fixed; top: 0; left: 0; width: 260px; height: 100vh;
    background: #1e293b; border-right: 1px solid #334155;
    z-index: 1000; overflow-y: auto;
}
.sidebar-brand { padding: 1.5rem; border-bottom: 1px solid #334155; }
.sidebar-brand h4 { margin: 0; font-weight: 700; color: #fff; }
.sidebar-brand small { color: #94a3b8; }
.sidebar-nav { padding: 1rem 0; }
.sidebar-nav a {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.75rem 1.5rem; color: #94a3b8;
    text-decoration: none; transition: all 0.2s;
}
.sidebar-nav a:hover, .sidebar-nav a.active {
    background: rgba(99, 102, 241, 0.1); color: #6366f1;
}
.sidebar-nav a i { width: 20px; text-align: center; }
.sidebar-nav .nav-section {
    padding: 0.5rem 1.5rem; font-size: 0.7rem;
    text-transform: uppercase; letter-spacing: 0.05em;
    color: #94a3b8; margin-top: 0.5rem;
}
/* Main content area - light theme */
.main-content { margin-left: 260px; }
</style>
<nav class="sidebar">
    <div class="sidebar-brand">
        <h4><i class="bi bi-flask me-2"></i>XPLabs</h4>
        <small><?= ucfirst($currentRole) ?> Portal</small>
    </div>
    <div class="sidebar-nav">
        <a href="dashboard_<?= $currentRole === 'student' ? 'student' : ($currentRole === 'admin' ? 'admin' : 'teacher') ?>.php" class="<?= in_array($currentPage, ['dashboard_student.php', 'dashboard_admin.php', 'dashboard_teacher.php']) ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>
        <a href="monitoring.php" class="<?= $currentPage === 'monitoring.php' ? 'active' : '' ?>">
            <i class="bi bi-display"></i> Lab Monitor
        </a>
        <a href="lab_seatplan.php" class="<?= $currentPage === 'lab_seatplan.php' ? 'active' : '' ?>">
            <i class="bi bi-layout-text-window-reverse"></i> Seat Plan
        </a>
        
        <?php if ($isAdmin): ?>
        <div class="nav-section">Management</div>
        <a href="admin_users.php" class="<?= $currentPage === 'admin_users.php' ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Users
        </a>
        <a href="admin_system.php" class="<?= $currentPage === 'admin_system.php' ? 'active' : '' ?>">
            <i class="bi bi-gear"></i> Lab Settings
        </a>
        <a href="inventory.php" class="<?= $currentPage === 'inventory.php' ? 'active' : '' ?>">
            <i class="bi bi-box-seam"></i> Inventory
        </a>
        <a href="incidents.php" class="<?= $currentPage === 'incidents.php' ? 'active' : '' ?>">
            <i class="bi bi-exclamation-triangle"></i> Incidents
        </a>
        <a href="announcements.php" class="<?= $currentPage === 'announcements.php' ? 'active' : '' ?>">
            <i class="bi bi-megaphone"></i> Announcements
        </a>
        <?php endif; ?>
        
        <div class="nav-section">Academic</div>
        <a href="courses.php" class="<?= $currentPage === 'courses.php' ? 'active' : '' ?>">
            <i class="bi bi-journal-bookmark"></i> Courses
        </a>
        <a href="assignments_manage.php" class="<?= $currentPage === 'assignments_manage.php' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Assignments
        </a>
        <a href="submissions.php" class="<?= $currentPage === 'submissions.php' ? 'active' : '' ?>">
            <i class="bi bi-upload"></i> Submissions
        </a>
        <a href="quizzes_manage.php" class="<?= $currentPage === 'quizzes_manage.php' ? 'active' : '' ?>">
            <i class="bi bi-question-circle"></i> Quizzes
        </a>
        <a href="attendance_history.php" class="<?= $currentPage === 'attendance_history.php' ? 'active' : '' ?>">
            <i class="bi bi-calendar-check"></i> Attendance
        </a>
        
        <div class="nav-section">Analytics</div>
        <a href="analytics.php" class="<?= $currentPage === 'analytics.php' ? 'active' : '' ?>">
            <i class="bi bi-graph-up"></i> Analytics
        </a>
        <a href="award_points.php" class="<?= $currentPage === 'award_points.php' ? 'active' : '' ?>">
            <i class="bi bi-award"></i> Award Points
        </a>
        
        <div class="nav-section">Gamification</div>
        <a href="leaderboard.php" class="<?= $currentPage === 'leaderboard.php' ? 'active' : '' ?>">
            <i class="bi bi-trophy"></i> Leaderboard
        </a>
        
        <?php if ($isAdmin): ?>
        <div class="nav-section">System</div>
        <a href="admin_logs.php" class="<?= $currentPage === 'admin_logs.php' ? 'active' : '' ?>">
            <i class="bi bi-activity"></i> Activity Logs
        </a>
        <?php endif; ?>
        
        <div class="nav-section mt-4">Account</div>
        <a href="#" onclick="document.getElementById('logout-form').submit(); return false;">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</nav>
<form id="logout-form" action="api/auth/logout.php" method="POST" style="display:none;"></form>