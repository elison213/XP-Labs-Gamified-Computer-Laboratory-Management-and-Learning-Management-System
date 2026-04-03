<?php
$ui_theme = $ui_theme ?? 'default';
$ribbons = [
  'student' => ['label' => 'Student hub', 'sub' => 'Your progress, tasks, and rank'],
  'teacher' => ['label' => 'Faculty desk', 'sub' => 'Class overview & assignment tools'],
  'admin' => ['label' => 'Control center', 'sub' => 'Users · logs · system'],
];
if (!isset($ribbons[$ui_theme])) {
  return;
}
$r = $ribbons[$ui_theme];
?>
<div class="shell-ribbon">
  <?php if ($ui_theme === 'admin'): ?>
    <span class="shell-dot" aria-hidden="true"></span>
  <?php endif; ?>
  <span><?php echo htmlspecialchars($r['label']); ?></span>
  <span class="opacity-75 fw-normal d-none d-md-inline">— <?php echo htmlspecialchars($r['sub']); ?></span>
</div>
