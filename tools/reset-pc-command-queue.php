<?php
/**
 * Fail all pending commands for a PC and reset server command cursor.
 * Usage: php tools/reset-pc-command-queue.php --pc_id=8
 */
require_once __DIR__ . '/../lib/Database.php';

use XPLabs\Lib\Database;

$pcId = 0;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--pc_id=')) {
        $pcId = (int) substr($arg, 8);
    }
}
if ($pcId <= 0) {
    fwrite(STDERR, "Usage: php tools/reset-pc-command-queue.php --pc_id=N\n");
    exit(1);
}

$db = Database::getInstance();
$stmt = $db->query(
    "UPDATE remote_commands
     SET status = 'failed', result = 'Queue reset (admin)', executed_at = NOW()
     WHERE pc_id = ? AND status = 'pending'",
    [$pcId]
);
echo 'Failed pending commands: ' . $stmt->rowCount() . PHP_EOL;

$db->query(
    'UPDATE lab_pcs SET last_command_cursor = 0, last_heartbeat_ack_id = NULL WHERE id = ?',
    [$pcId]
);
echo "Reset lab_pcs command cursor for pc_id=$pcId" . PHP_EOL;
echo PHP_EOL;
echo 'On the lab PC (elevated PowerShell), reset the agent cursor too:' . PHP_EOL;
echo '  powershell -ExecutionPolicy Bypass -File "C:\Program Files\XPLabsAgent\Repair-PcCommandCursor.ps1" -ResetToZero' . PHP_EOL;
echo 'Then: schtasks /Run /TN XPLabsAgentLoop' . PHP_EOL;
