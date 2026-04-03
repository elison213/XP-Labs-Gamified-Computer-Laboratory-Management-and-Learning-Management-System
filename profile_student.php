<?php
$page_title = 'Profile';
$page_id = 'student-profile';
$nav_user = 'Juan (Student)';
$nav_role = 'student';
$ui_theme = 'student';
$active_page = 'profile_student.php';
?>
<?php include 'components/head.php'; ?>
<?php include 'components/navbar.php'; ?>

<div class="d-flex">
  <?php include 'components/sidebar.php'; ?>

  <main class="dashboard-main p-4">
    <?php include 'components/shell_ribbon.php'; ?>
    <h2 class="h4 mb-4 reveal">Profile</h2>

    <div class="row g-4">
      <div class="col-md-4 reveal">
        <div class="card shadow-sm text-center p-4" data-api-profile="/profile">
          <div class="rounded-circle bg-light border mx-auto mb-3 d-flex align-items-center justify-content-center"
               style="width: 96px; height: 96px; font-size: 2.5rem;">&#128100;</div>
          <h3 class="h5 mb-0">Juan Dela Cruz</h3>
          <p class="small text-secondary mb-0">Student ID: 2026-0142</p>
        </div>
      </div>
      <div class="col-md-8 reveal">
        <div class="card shadow-sm">
          <div class="card-body">
            <form id="form-profile" data-api-submit="PATCH" data-api-endpoint="/profile" onsubmit="return false;">
              <div class="mb-3">
                <label class="form-label small text-secondary">Display name</label>
                <input type="text" class="form-control" name="display_name" value="Juan Dela Cruz" autocomplete="name">
              </div>
              <div class="mb-3">
                <label class="form-label small text-secondary">Email</label>
                <input type="email" class="form-control" name="email" value="juan@school.edu" autocomplete="email">
              </div>
              <div class="mb-3">
                <label class="form-label small text-secondary">Section</label>
                <input type="text" class="form-control" value="10-A" readonly>
              </div>
              <button type="button" class="btn btn-primary" disabled title="Wire to API">Save changes</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<?php include 'components/footer.php'; ?>
