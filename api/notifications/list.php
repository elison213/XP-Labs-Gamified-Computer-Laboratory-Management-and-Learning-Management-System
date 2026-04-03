<?php
/**
 * XPLabs API - GET /api/notifications
 * Get user's notifications.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';

use XPLabs\Lib\Auth;

Auth::require();

$userId = Auth::id();
$db = \XPLabs\Lib\Database::getInstance();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;

$total = (int) $db->fetchOne("SELECT COUNT(*) FROM notifications WHERE user_id = ?", [$userId]);
$notifications = $db->fetchAll(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
    [$userId]
);

// Mark as read
if (!empty($notifications)) {
    $ids = array_column($notifications, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $db->query("UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders)", $ids);
}

$unreadCount = (int) $db->fetchOne("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$userId]);

echo json_encode([
    'notifications' => $notifications,
    'unread_count' => $unreadCount,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage,
    'last_page' => (int) ceil($total / $perPage),
]);