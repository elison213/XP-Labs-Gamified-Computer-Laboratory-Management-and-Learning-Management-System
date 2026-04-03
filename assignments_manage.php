<?php
$page_title = 'Manage assignments';
$page_id = 'teacher-assignments-manage';
$nav_user = 'Ms. Reyes (Teacher)';
$nav_role = 'teacher';
$ui_theme = 'teacher';
$active_page = 'assignments_manage.php';
?>
<?php include 'components/head.php'; ?>
<?php include 'components/navbar.php'; ?>

<div class="d-flex">
  <?php include 'components/sidebar.php'; ?>

  <main class="dashboard-main p-4">
    <?php include 'components/shell_ribbon.php'; ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 reveal">
      <div>
        <h2 class="h4 mb-1">Assignment management</h2>
        <p class="text-secondary small mb-0">Create, edit, delete — list from <code class="small">GET /assignments</code></p>
      </div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAssignmentCreate">
        New assignment
      </button>
    </div>

    <div class="card shadow-sm reveal" data-api-list="/assignments" id="teacher-assignments-table">
      <div class="table-responsive">
        <table class="table table-hover mb-0 small align-middle">
          <thead class="table-light">
            <tr>
              <th>Title</th>
              <th>Due</th>
              <th>Submissions</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr data-assignment-id="1">
              <td>HTML structure lab</td>
              <td>Mar 30</td>
              <td>18</td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Edit</button>
                <button type="button" class="btn btn-sm btn-outline-danger" disabled>Delete</button>
              </td>
            </tr>
            <tr data-assignment-id="2">
              <td>CSS flexbox practice</td>
              <td>Apr 5</td>
              <td>12</td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Edit</button>
                <button type="button" class="btn btn-sm btn-outline-danger" disabled>Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<div class="modal fade" id="modalAssignmentCreate" tabindex="-1" aria-labelledby="modalAssignmentCreateLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalAssignmentCreateLabel">New assignment</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="form-assignment-create" data-api-submit="POST" data-api-endpoint="/assignments" onsubmit="return false;">
          <div class="mb-3">
            <label class="form-label small text-secondary">Title</label>
            <input type="text" class="form-control" name="title" required>
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary">Description</label>
            <textarea class="form-control" name="description" rows="4"></textarea>
          </div>
          <div class="row g-2">
            <div class="col-md-6 mb-3">
              <label class="form-label small text-secondary">Due date</label>
              <input type="date" class="form-control" name="due_at">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label small text-secondary">Points</label>
              <input type="number" class="form-control" name="points" value="100" min="0">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" disabled title="Connect API">Create</button>
      </div>
    </div>
  </div>
</div>

<?php include 'components/footer.php'; ?>
