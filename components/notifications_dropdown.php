<?php
/** Bell + dropdown; populate via JS using data-api-endpoint (default GET /notifications). */
$notifications_endpoint = $notifications_endpoint ?? '/notifications';
?>
<div class="dropdown nav-notifications"
     data-api-mount="notifications"
     data-api-endpoint="<?php echo htmlspecialchars($notifications_endpoint, ENT_QUOTES, 'UTF-8'); ?>">
  <button type="button"
          class="btn btn-link nav-notifications-btn text-white-50 p-1 border-0 position-relative"
          id="nav-notifications-btn"
          data-bs-toggle="dropdown"
          data-bs-auto-close="outside"
          aria-expanded="false"
          aria-label="Notifications">
    <span class="d-inline-block fs-5 lh-1">&#128276;</span>
    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
          data-notification-count
          aria-live="polite">0</span>
  </button>
  <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-0 overflow-hidden" style="min-width: 300px; max-height: 320px;">
    <li class="px-3 py-2 border-bottom small fw-semibold text-secondary bg-light">Notifications</li>
    <li data-notifications-list>
      <span class="dropdown-item-text small text-muted py-3 px-3 d-block" data-api-placeholder>
        Connect your API — this list will load from <code class="small">GET <?php echo htmlspecialchars($notifications_endpoint); ?></code>
      </span>
    </li>
    <li class="border-top"><span class="dropdown-item-text small text-center text-muted py-2">UI only</span></li>
  </ul>
</div>
