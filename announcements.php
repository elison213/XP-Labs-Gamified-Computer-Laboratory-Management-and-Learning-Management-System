<?php
$page_title = 'Announcements';
$page_id = 'teacher-announcements';
$nav_user = 'Ms. Reyes (Teacher)';
$nav_role = 'teacher';
$ui_theme = 'teacher';
$active_page = 'announcements.php';
?>
<?php include 'components/head.php'; ?>
<?php include 'components/navbar.php'; ?>

<div class="d-flex">
  <?php include 'components/sidebar.php'; ?>

  <main class="dashboard-main p-4">
    <?php include 'components/shell_ribbon.php'; ?>
    <h2 class="h4 mb-4 reveal">Announcements</h2>

    <div class="card shadow-sm mb-4 reveal">
      <div class="card-header bg-white fw-semibold">Post announcement</div>
      <div class="card-body">
        <form id="form-announcement" data-api-submit="POST" data-api-endpoint="/announcements" onsubmit="return false;">
          <div class="mb-3">
            <label class="form-label small text-secondary">Title</label>
            <input type="text" class="form-control" name="title" placeholder="Lab hours this week">
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary">Body</label>
            <textarea class="form-control" name="body" rows="3" placeholder="Message to your classes…"></textarea>
          </div>
          <button type="button" class="btn btn-primary" disabled title="Connect API">Publish</button>
        </form>
      </div>
    </div>

    <div class="card shadow-sm reveal" data-api-list="/announcements">
      <div class="card-header bg-white fw-semibold">Posted</div>
      <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
          <div>
            <div class="fw-semibold">Lab closed Thursday PM</div>
            <div class="small text-secondary">Posted Mar 26 · applies to all sections</div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-danger" disabled>Delete</button>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
          <div>
            <div class="fw-semibold">Assignment 2 extended</div>
            <div class="small text-secondary">Posted Mar 25</div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-danger" disabled>Delete</button>
        </li>
      </ul>
    </div>
  </main>
</div>

<?php include 'components/footer.php'; ?>
