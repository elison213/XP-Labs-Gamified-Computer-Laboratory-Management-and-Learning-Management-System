<?php
$page_title = 'Leaderboard';
$view = $_GET['as'] ?? 'student';
if ($view === 'teacher') {
  $nav_user = 'Ms. Reyes (Teacher)';
  $nav_role = 'teacher';
  $ui_theme = 'teacher';
} elseif ($view === 'admin') {
  $nav_user = 'Admin';
  $nav_role = 'admin';
  $ui_theme = 'admin';
} else {
  $nav_user = 'Juan (Student)';
  $nav_role = 'student';
  $ui_theme = 'student';
}
$active_page = 'leaderboard.php';
$page_id = 'leaderboard-' . $nav_role;
?>
<?php include 'components/head.php'; ?>
<?php include 'components/navbar.php'; ?>

<div class="d-flex">
  <?php include 'components/sidebar.php'; ?>

  <main class="dashboard-main p-4">
    <?php include 'components/shell_ribbon.php'; ?>
    <h2 class="h4 mb-4 reveal">Leaderboard</h2>

    <div class="leader-podium row g-3 justify-content-center mb-4 reveal-stagger">
      <div class="col-md-4">
        <div class="card rank-1 shadow-sm text-center h-100 border-warning border-2">
          <div class="card-body py-4">
            <div class="display-6 mb-2">🥇</div>
            <h3 class="h5 mb-1">Juan</h3>
            <p class="text-primary fw-bold mb-0">150 pts</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card rank-2 shadow-sm text-center h-100">
          <div class="card-body py-4">
            <div class="display-6 mb-2">🥈</div>
            <h3 class="h5 mb-1">Maria</h3>
            <p class="text-secondary fw-bold mb-0">120 pts</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card rank-3 shadow-sm text-center h-100">
          <div class="card-body py-4">
            <div class="display-6 mb-2">🥉</div>
            <h3 class="h5 mb-1">Alex</h3>
            <p class="text-secondary fw-bold mb-0">100 pts</p>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm reveal">
      <div class="card-header bg-white fw-semibold">Full ranking</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 small">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Name</th>
              <th class="text-end">Points</th>
            </tr>
          </thead>
          <tbody>
            <tr><td>1</td><td>Juan</td><td class="text-end">150</td></tr>
            <tr><td>2</td><td>Maria</td><td class="text-end">120</td></tr>
            <tr><td>3</td><td>Alex</td><td class="text-end">100</td></tr>
            <tr><td>4</td><td>Sam</td><td class="text-end">88</td></tr>
            <tr><td>5</td><td>Kim</td><td class="text-end">72</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<?php include 'components/footer.php'; ?>
