<?php
$page_title = 'Submit work';
$page_id = 'student-submission';
$nav_user = 'Juan (Student)';
$nav_role = 'student';
$ui_theme = 'student';
$active_page = 'submission.php';
$aid = isset($_GET['assignment']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['assignment']) : '1';
?>
<?php include 'components/head.php'; ?>
<?php include 'components/navbar.php'; ?>

<div class="d-flex">
  <?php include 'components/sidebar.php'; ?>

  <main class="dashboard-main p-4">
    <?php include 'components/shell_ribbon.php'; ?>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3 reveal">
      <div>
        <h2 class="h4 mb-1">Submit assignment</h2>
        <p class="text-secondary small mb-0">Assignment ID: <code><?php echo htmlspecialchars($aid); ?></code></p>
      </div>
      <a href="assignments.php" class="btn btn-outline-secondary btn-sm">Back to list</a>
    </div>

    <div class="card shadow-sm reveal">
      <div class="card-body">
        <h3 class="h6 mb-3">HTML structure lab</h3>
        <form id="form-submission"
              data-api-submit="POST"
              data-api-endpoint="/assignments/<?php echo htmlspecialchars($aid); ?>/submissions"
              onsubmit="return false;">
          <div class="mb-3">
            <label class="form-label small text-secondary">Comments for teacher</label>
            <textarea class="form-control" name="comment" rows="3" placeholder="Optional notes…"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary">Upload</label>
            <input type="file" class="form-control" name="file" disabled>
            <p class="form-text small">Use <code>multipart/form-data</code> when API is ready.</p>
          </div>
          <button type="button" class="btn btn-primary" disabled title="Connect API">Submit work</button>
        </form>
      </div>
    </div>
  </main>
</div>

<?php include 'components/footer.php'; ?>
