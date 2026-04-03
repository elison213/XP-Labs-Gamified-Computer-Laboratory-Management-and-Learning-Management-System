<?php
$page_title = 'Assignments';
$page_id = 'student-assignments';
$nav_user = 'Juan (Student)';
$nav_role = 'student';
$ui_theme = 'student';
$active_page = 'assignments.php';
?>
<?php include 'components/head.php'; ?>
<?php include 'components/navbar.php'; ?>

<div class="d-flex">
  <?php include 'components/sidebar.php'; ?>

  <main class="dashboard-main p-4">
    <?php include 'components/shell_ribbon.php'; ?>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3 reveal">
      <div>
        <h2 class="h4 mb-1">Assignments</h2>
        <p class="text-secondary small mb-0">List loads from <code class="small">GET /assignments</code></p>
      </div>
    </div>

    <div class="row g-3 reveal-stagger" id="student-assignments-list" data-api-list="/assignments">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
              <h3 class="h5 mb-0">HTML structure lab</h3>
              <span class="badge text-bg-secondary align-self-start">Due Mar 30</span>
            </div>
            <p class="text-secondary small mb-3">Build a page using header, main, and footer. Include one list and one link.</p>
            <div class="d-flex flex-wrap gap-2">
              <a href="submission.php?assignment=1" class="btn btn-primary btn-sm">Submit (full page)</a>
              <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#submitModal">Quick submit</button>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
              <h3 class="h5 mb-0">CSS flexbox practice</h3>
              <span class="badge text-bg-secondary align-self-start">Due Apr 5</span>
            </div>
            <p class="text-secondary small mb-3">Center a card both vertically and horizontally using flexbox.</p>
            <div class="d-flex flex-wrap gap-2">
              <a href="submission.php?assignment=2" class="btn btn-primary btn-sm">Submit (full page)</a>
              <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#submitModal">Quick submit</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<div class="modal fade" id="submitModal" tabindex="-1" aria-labelledby="submitModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="submitModalLabel">Submit assignment</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form data-api-submit="POST" data-api-endpoint="/assignments/:id/submissions" onsubmit="return false;">
          <div class="mb-3">
            <label class="form-label small text-secondary">Notes (optional)</label>
            <textarea class="form-control" rows="3" placeholder="Short comment for your teacher…"></textarea>
          </div>
          <div class="mb-0">
            <label class="form-label small text-secondary">Upload file</label>
            <input type="file" class="form-control" disabled>
            <p class="form-text small mb-0">Use multipart when API is ready.</p>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" disabled>Submit</button>
      </div>
    </div>
  </div>
</div>

<?php include 'components/footer.php'; ?>
