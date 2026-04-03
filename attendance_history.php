<?php
$page_title = 'Attendance history';
$page_id = 'student-attendance';
$nav_user = 'Juan (Student)';
$nav_role = 'student';
$ui_theme = 'student';
$active_page = 'attendance_history.php';
?>
<?php include 'components/head.php'; ?>
<?php include 'components/navbar.php'; ?>

<div class="d-flex">
  <?php include 'components/sidebar.php'; ?>

  <main class="dashboard-main p-4">
    <?php include 'components/shell_ribbon.php'; ?>
    <h2 class="h4 mb-2 reveal">Attendance history</h2>
    <p class="text-secondary small mb-4 reveal">Static demo rows — replace via <code class="small">GET /attendance/history</code>.</p>

    <div class="card shadow-sm reveal"
         id="attendance-history-table"
         data-api-list="/attendance/history">
      <div class="table-responsive">
        <table class="table table-hover mb-0 small">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <th>Session</th>
              <th>Method</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody data-api-placeholder-rows>
            <tr>
              <td>Mar 28, 2026</td>
              <td>Morning lab</td>
              <td>QR</td>
              <td><span class="badge text-bg-success">Present</span></td>
            </tr>
            <tr>
              <td>Mar 27, 2026</td>
              <td>Morning lab</td>
              <td>Manual</td>
              <td><span class="badge text-bg-success">Present</span></td>
            </tr>
            <tr>
              <td>Mar 26, 2026</td>
              <td>Morning lab</td>
              <td>QR</td>
              <td><span class="badge text-bg-warning text-dark">Late</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<?php include 'components/footer.php'; ?>
