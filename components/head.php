<?php
$page_title = $page_title ?? 'XPLabs';
$page_id = $page_id ?? '';
$ui_theme = $ui_theme ?? 'default';
$allowed_ui = ['default', 'student', 'teacher', 'admin'];
if (!in_array($ui_theme, $allowed_ui, true)) {
  $ui_theme = 'default';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($page_title); ?> — XPLabs</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="stylesheet" href="assets/css/themes.css">
</head>
<body class="bg-app theme-<?php echo htmlspecialchars($ui_theme, ENT_QUOTES, 'UTF-8'); ?>"
      <?php if ($page_id !== ''): ?>id="page-<?php echo htmlspecialchars(preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '-', $page_id)), ENT_QUOTES, 'UTF-8'); ?>" data-page="<?php echo htmlspecialchars($page_id, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
