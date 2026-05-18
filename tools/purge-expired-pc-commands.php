<?php
/**
 * Mark expired pending remote_commands as failed so the queue is clean.
 * Usage: C:\xampp\php\php.exe tools\purge-expired-pc-commands.php [--pc_id=8]
 */
require_once __DIR__ . '/../lib/Database.php';

use XPLabs\Lib\Database;

$pcId = 0;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--pc_id=')) {
        $pcId = (int) substr($arg, 8);
    }
}

$db = Database::getInstance();
$sql = "UPDATE remote_commands
        SET status = 'failed', result = 'Expired (purged)', executed_at = NOW()
        WHERE status = 'pending'
          AND expires_at IS NOT NULL
          AND expires_at < NOW()";
$params = [];
if ($pcId > 0) {
    $sql .= ' AND pc_id = ?';
    $params[] = $pcId;
}
$stmt = $db->query($sql, $params);
$n = $stmt->rowCount();
echo "Purged expired pending commands: " . (int) $n . PHP_EOL;

$pending = $db->fetchOne(
    $pcId > 0
        ? "SELECT COUNT(*) FROM remote_commands WHERE pc_id = ? AND status = 'pending'"
        : "SELECT COUNT(*) FROM remote_commands WHERE status = 'pending'",
    $pcId > 0 ? [$pcId] : []
);
echo "Remaining pending: " . (int) $pending . PHP_EOL;
