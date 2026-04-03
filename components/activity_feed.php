<?php
$feed_title = $feed_title ?? 'Activity feed';
$feed_id = $feed_id ?? 'activity-feed';
$feed_endpoint = $feed_endpoint ?? '/activity';
?>
<div class="card shadow-sm reveal" id="<?php echo htmlspecialchars($feed_id, ENT_QUOTES, 'UTF-8'); ?>"
     data-api-list="<?php echo htmlspecialchars($feed_endpoint, ENT_QUOTES, 'UTF-8'); ?>"
     title="Replace contents via GET <?php echo htmlspecialchars($feed_endpoint); ?>">
  <div class="card-header bg-white fw-semibold">
    <span><?php echo htmlspecialchars($feed_title); ?></span>
  </div>
  <ul class="list-group list-group-flush" data-activity-items>
    <li class="list-group-item small" data-api-placeholder>Logged in at 9:05 AM</li>
    <li class="list-group-item small" data-api-placeholder>Earned <strong>+5</strong> points for attendance</li>
    <li class="list-group-item small" data-api-placeholder>Submitted &ldquo;HTML structure lab&rdquo;</li>
  </ul>
</div>
