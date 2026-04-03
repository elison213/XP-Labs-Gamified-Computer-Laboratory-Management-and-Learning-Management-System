<?php
$nav_user = $nav_user ?? 'Guest';
$ui_theme = $ui_theme ?? 'default';
$role_pills = [
  'student' => 'Student',
  'teacher' => 'Faculty',
  'admin' => 'Admin',
];
$show_notifications = $show_notifications ?? in_array($ui_theme, ['student', 'teacher', 'admin'], true);
?>
<nav class="navbar navbar-dark nav-xp shadow-sm">
  <div class="container-fluid d-flex align-items-center justify-content-between gap-2">
    <a class="navbar-brand fw-semibold mb-0" href="index.php">XPLabs</a>
    <div class="d-flex align-items-center gap-1 gap-sm-2 flex-shrink-0">
      <?php if ($show_notifications && isset($role_pills[$ui_theme])): ?>
        <?php include __DIR__ . '/notifications_dropdown.php'; ?>
      <?php endif; ?>
      <?php if (isset($role_pills[$ui_theme])): ?>
        <span class="nav-role-pill d-none d-sm-inline"><?php echo htmlspecialchars($role_pills[$ui_theme]); ?></span>
      <?php endif; ?>
      <span class="navbar-text text-white-50 small text-end mb-0"><?php echo htmlspecialchars($nav_user); ?></span>
    </div>
  </div>
</nav>
