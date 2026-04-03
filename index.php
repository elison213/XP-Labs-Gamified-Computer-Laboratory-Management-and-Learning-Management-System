<?php
$page_title = 'Home';
$page_id = 'home';
$ui_theme = 'default';
?>
<?php include 'components/head.php'; ?>

<nav class="navbar navbar-dark nav-xp shadow-sm">
  <div class="container">
    <span class="navbar-brand fw-semibold">XPLabs</span>
    <a class="btn btn-outline-light btn-sm" href="login.php">Login</a>
  </div>
</nav>

<div class="hero-xp">
  <div class="hero-glow" aria-hidden="true"></div>
  <div class="container py-5">
    <div class="row justify-content-center hero-inner">
      <div class="col-lg-9 text-center">
        <p class="hero-badge mb-3 reveal">Lab-ready · Attendance &amp; quests</p>
        <h1 class="display-5 hero-title mb-3">Computer Lab System</h1>
        <p class="lead text-secondary hero-lead mb-4 px-md-3">
          Check in fast, ship assignments, and climb the board — built for real class flow, with room to wire up the backend later.
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-2 gap-md-3 hero-actions">
          <a href="login.php" class="btn btn-primary btn-lg px-4">Go to Login</a>
          <a href="dashboard_student.php" class="btn btn-outline-secondary btn-lg">Student hub</a>
          <a href="dashboard_teacher.php" class="btn btn-outline-secondary btn-lg">Teacher hub</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'components/footer.php'; ?>
