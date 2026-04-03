<?php
/**
 * XPLabs - Admin/Teacher Sidebar Component
 * Usage: Include this file after $role is defined
 */
$currentRole = $role ?? 'admin';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="sidebar">
    <div class="sidebar-brand">
        <h4><i class="bi bi-flask me-2"></i>XPLabs</h4>
        <small><?= ucfirst($currentRole) ?> Portal</small>
    </div>
    <div class="sidebar-nav">
        <a href="dashboard_<?= $currentRole === 'student' ? 'student' : ($currentRole === 'admin' ? 'admin' : 'teacher') ?>.php" class="<?= $currentPage === 'dashboard_admin.php' || $currentPage === 'dashboard_teacher.php' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>
        <a href="monitoring.php" class="<?= $currentPage === 'monitoring.php' ? 'active' : '' ?>">
            <i class="bi bi-display"></i> Lab Monitor
        </a>
        <a href="lab_seatplan.php" class="<?= $currentPage === 'lab_seatplan.php' ? 'active' : '' ?>">
            <i class="bi bi-layout-text-window-reverse"></i> Seat Plan
        </a>
        
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
        
        <div class="nav-section">Academic</div>
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
        
        <div class="nav-section">Gamification</div>
        <a href="leaderboard.php" class="<?= $currentPage === 'leaderboard.php' ? 'active' : '' ?>">
            <i class="bi bi-trophy"></i> Leaderboard
        </a>
        
        <div class="nav-section">System</div>
        <a href="admin_logs.php" class="<?= $currentPage === 'admin_logs.php' ? 'active' : '' ?>">
            <i class="bi bi-activity"></i> Activity Logs
        </a>
        
        <div class="nav-section mt-4">Account</div>
        <a href="api/auth/logout.php">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</nav>