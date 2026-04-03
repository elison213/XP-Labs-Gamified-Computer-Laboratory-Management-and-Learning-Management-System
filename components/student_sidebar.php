<nav class="sidebar">
    <div class="sidebar-brand">
        <h4><i class="bi bi-flask me-2"></i>XPLabs</h4>
        <small>Student Portal</small>
    </div>
    <div class="sidebar-nav">
        <a href="dashboard_student.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard_student.php' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>
        <a href="announcements.php" class="<?= basename($_SERVER['PHP_SELF']) === 'announcements.php' ? 'active' : '' ?>">
            <i class="bi bi-megaphone"></i> Announcements
        </a>
        
        <div class="nav-section">Learning</div>
        <a href="assignments.php" class="<?= basename($_SERVER['PHP_SELF']) === 'assignments.php' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Assignments
        </a>
        <a href="submissions.php" class="<?= basename($_SERVER['PHP_SELF']) === 'submissions.php' ? 'active' : '' ?>">
            <i class="bi bi-upload"></i> My Submissions
        </a>
        <a href="quizzes_manage.php" class="<?= basename($_SERVER['PHP_SELF']) === 'quizzes_manage.php' ? 'active' : '' ?>">
            <i class="bi bi-question-circle"></i> Quizzes
        </a>
        
        <div class="nav-section">Lab</div>
        <a href="lab_seatplan.php" class="<?= basename($_SERVER['PHP_SELF']) === 'lab_seatplan.php' ? 'active' : '' ?>">
            <i class="bi bi-layout-text-window-reverse"></i> Seat Plan
        </a>
        <a href="attendance_history.php" class="<?= basename($_SERVER['PHP_SELF']) === 'attendance_history.php' ? 'active' : '' ?>">
            <i class="bi bi-calendar-check"></i> Attendance
        </a>
        
        <div class="nav-section">Achievements</div>
        <a href="leaderboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'leaderboard.php' ? 'active' : '' ?>">
            <i class="bi bi-trophy"></i> Leaderboard
        </a>
        <a href="profile_student.php" class="<?= basename($_SERVER['PHP_SELF']) === 'profile_student.php' ? 'active' : '' ?>">
            <i class="bi bi-person"></i> My Profile
        </a>
        
        <div class="nav-section mt-4">Account</div>
        <a href="api/auth/logout.php">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</nav>