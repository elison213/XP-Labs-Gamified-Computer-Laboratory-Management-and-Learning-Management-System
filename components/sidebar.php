<?php
$nav_role = $nav_role ?? 'student';
$active_page = $active_page ?? '';

$items = [
  'student' => [
    ['dashboard_student.php', 'Dashboard'],
    ['assignments.php', 'Assignments'],
    ['submissions.php', 'Submissions'],
    ['attendance_history.php', 'Attendance'],
    ['leaderboard.php', 'Leaderboard'],
    ['profile_student.php', 'Profile'],
  ],
  'teacher' => [
    ['dashboard_teacher.php', 'Dashboard'],
    ['assignments_manage.php', 'Assignments'],
    ['monitoring.php', 'Monitoring'],
    ['announcements.php', 'Announcements'],
    ['submissions.php?as=teacher', 'Submissions'],
    ['leaderboard.php?as=teacher', 'Leaderboard'],
  ],
  'admin' => [
    ['dashboard_admin.php', 'Dashboard'],
    ['admin_users.php', 'Users'],
    ['admin_logs.php', 'Logs'],
    ['admin_system.php', 'System'],
    ['monitoring.php?as=admin', 'Live activity'],
    ['leaderboard.php?as=admin', 'Leaderboard'],
  ],
];

$sidebar_titles = [
  'student' => 'Student hub',
  'teacher' => 'Faculty desk',
  'admin' => 'Control center',
];

$links = $items[$nav_role] ?? $items['student'];
$sidebar_title = $sidebar_titles[$nav_role] ?? 'Menu';
?>
<aside class="sidebar border-end sidebar-xp" data-nav-role="<?php echo htmlspecialchars($nav_role, ENT_QUOTES, 'UTF-8'); ?>">
  <div class="sidebar-brand-area p-3 small text-secondary text-uppercase">
    <?php echo htmlspecialchars($sidebar_title); ?>
  </div>
  <ul class="nav flex-column p-2">
    <?php foreach ($links as $link): ?>
      <?php
        $href = $link[0];
        $label = $link[1];
        $hrefPath = explode('?', $href, 2)[0];
        $is_active = ($active_page === $hrefPath);
      ?>
      <li class="nav-item">
        <a class="nav-link rounded <?php echo $is_active ? 'active fw-semibold' : ''; ?>"
           href="<?php echo htmlspecialchars($href); ?>"
           data-nav-path="<?php echo htmlspecialchars($hrefPath); ?>">
          <?php echo htmlspecialchars($label); ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</aside>
