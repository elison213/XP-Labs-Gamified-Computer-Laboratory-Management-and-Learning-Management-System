<?php
/**
 * CLI: inspect pending remote commands and heartbeat tables for a lab PC.
 * Usage: php tools/check-pc-commands.php --hostname=LAB-PC-01
 */
require_once __DIR__ . '/../lib/Database.php';

use XPLabs\Lib\Database;

$hostname = '';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--hostname=')) {
        $hostname = substr($arg, 11);
    }
}
if ($hostname === '') {
    fwrite(STDERR, "Usage: php tools/check-pc-commands.php --hostname=YOUR-PC-NAME\n");
    exit(1);
}

$db = Database::getInstance();
$pc = $db->fetch('SELECT * FROM lab_pcs WHERE hostname = ?', [$hostname]);
if (!$pc) {
    fwrite(STDERR, "PC not found: $hostname\n");
    exit(2);
}

$pcId = (int) $pc['id'];
echo "PC id=$pcId hostname={$pc['hostname']} status={$pc['status']}\n";
echo "last_command_cursor=" . ($pc['last_command_cursor'] ?? 'null') . "\n";
echo "pc_heartbeat_receipts table: " . ($db->tableExists('pc_heartbeat_receipts') ? 'yes' : 'NO — run migration 049') . "\n";
echo "pc_message_threads table: " . ($db->tableExists('pc_message_threads') ? 'yes' : 'NO') . "\n\n";

$pending = $db->fetchAll(
    "SELECT id, command_type, status, created_at, expires_at
     FROM remote_commands
     WHERE pc_id = ? AND status = 'pending'
     ORDER BY id ASC",
    [$pcId]
);
echo 'Pending commands: ' . count($pending) . "\n";
foreach ($pending as $row) {
    echo "  #{$row['id']} {$row['command_type']} created={$row['created_at']} expires={$row['expires_at']}\n";
}

$stuck = $db->fetchAll(
    "SELECT id, command_type, status, created_at
     FROM remote_commands
     WHERE pc_id = ? AND status = 'pending' AND id <= ?",
    [$pcId, (int) ($pc['last_command_cursor'] ?? 0)]
);
if ($stuck) {
    echo "\nWARNING: " . count($stuck) . " pending command(s) at or below server cursor (agent may skip these).\n";
}
