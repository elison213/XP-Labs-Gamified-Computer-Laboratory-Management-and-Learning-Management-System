<?php
$page_title = 'System logs';
$page_id = 'admin-logs';
$nav_user = 'Admin';
$nav_role = 'admin';
$ui_theme = 'admin';
$active_page = 'admin_logs.php';
?>
<?php include 'components/head.php'; ?>
<?php include 'components/navbar.php'; ?>

<div class="d-flex">
  <?php include 'components/sidebar.php'; ?>

  <main class="dashboard-main p-4">
    <?php include 'components/shell_ribbon.php'; ?>
    <h2 class="h4 mb-2 reveal">System logs</h2>
    <p class="text-secondary small mb-4 reveal">Filter bar is UI-only — data from <code class="small">GET /logs</code></p>

    <div class="card shadow-sm mb-3 reveal">
      <div class="card-body py-2">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label small text-secondary mb-0">From</label>
            <input type="date" class="form-control form-control-sm" disabled>
          </div>
          <div class="col-md-3">
            <label class="form-label small text-secondary mb-0">To</label>
            <input type="date" class="form-control form-control-sm" disabled>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-secondary mb-0">Search</label>
            <input type="search" class="form-control form-control-sm" placeholder="user, action…" disabled>
          </div>
          <div class="col-md-2">
            <button type="button" class="btn btn-primary btn-sm w-100" disabled>Apply</button>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm reveal" data-api-list="/logs">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Time</th>
              <th>User</th>
              <th>Action</th>
              <th>Detail</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="text-nowrap">2026-03-29 10:12:04</td>
              <td>admin</td>
              <td>VIEW</td>
              <td>dashboard</td>
            </tr>
            <tr>
              <td class="text-nowrap">2026-03-29 09:05:22</td>
              <td>Juan</td>
              <td>QR_CHECKIN</td>
              <td>lab-1</td>
            </tr>
            <tr>
              <td class="text-nowrap">2026-03-28 16:40:11</td>
              <td>Ms. Reyes</td>
              <td>ASSIGNMENT_CREATE</td>
              <td>id=2</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<?php include 'components/footer.php'; ?>
