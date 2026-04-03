<?php
$page_title = 'Submissions';
$view = $_GET['as'] ?? 'student';
if ($view === 'teacher') {
  $nav_user = 'Ms. Reyes (Teacher)';
  $nav_role = 'teacher';
  $ui_theme = 'teacher';
  $page_id = 'teacher-submissions';
} else {
  $nav_user = 'Juan (Student)';
  $nav_role = 'student';
  $ui_theme = 'student';
  $page_id = 'student-submissions';
}
$active_page = 'submissions.php';
?>
<?php include 'components/head.php'; ?>
<?php include 'components/navbar.php'; ?>

<div class="d-flex">
  <?php include 'components/sidebar.php'; ?>

  <main class="dashboard-main p-4">
    <?php include 'components/shell_ribbon.php'; ?>
    <div class="reveal">
      <h2 class="h4 mb-2"><?php echo $nav_role === 'teacher' ? 'Submissions inbox' : 'My submissions'; ?></h2>
      <p class="text-secondary small mb-4">
        <?php echo $nav_role === 'teacher'
          ? 'Review student work — hook to GET /submissions'
          : 'Your submitted work — hook to GET /submissions'; ?>
      </p>
    </div>

    <div class="reveal-stagger" data-api-list="/submissions">
      <div class="card shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <h3 class="h6 mb-1">HTML structure lab</h3>
            <p class="small text-secondary mb-0">Submitted Mar 28 · <span class="text-success">Graded</span></p>
          </div>
          <span class="badge text-bg-primary">92/100</span>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <h3 class="h6 mb-1">CSS flexbox practice</h3>
            <p class="small text-secondary mb-0">Not submitted</p>
          </div>
          <a href="assignments.php" class="btn btn-sm btn-outline-primary">Go to assignments</a>
        </div>
      </div>
    </div>
  </main>
</div>

<?php include 'components/footer.php'; ?>
