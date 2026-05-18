<?php
/**
 * Queue a remote lock command (opens lockscreen on lab PC).
 *
 *   C:\xampp\php\php.exe tools\queue-remote-lock.php --hostname=LAB-PC-01
 *   C:\xampp\php\php.exe tools\queue-remote-lock.php --pc-id=3
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../services/PCService.php';

use XPLabs\Lib\Database;
use XPLabs\Services\PCService;

function arg(string $name): ?string
{
    global $argv;
    $prefix = '--' . $name . '=';
    foreach ($argv as $a) {
        if (strpos($a, $prefix) === 0) {
            return substr($a, strlen($prefix));
        }
    }
    return null;
}

$hostname = arg('hostname');
$pcId = (int) (arg('pc-id') ?? '0');
$db = Database::getInstance();

if ($pcId <= 0 && $hostname) {
    $row = $db->fetch('SELECT id FROM lab_pcs WHERE hostname = ? LIMIT 1', [$hostname]);
    if (!$row) {
        fwrite(STDERR, "No PC with hostname: {$hostname}\n");
        exit(1);
    }
    $pcId = (int) $row['id'];
}

if ($pcId <= 0) {
    fwrite(STDERR, "Usage: php tools/queue-remote-lock.php --hostname=NAME | --pc-id=N\n");
    exit(1);
}

$svc = new PCService();
$result = $svc->queueCommand($pcId, 0, 'lock', ['source' => 'cli_test'], 300);

if (!($result['success'] ?? false)) {
    fwrite(STDERR, 'Failed: ' . ($result['error'] ?? 'unknown') . "\n");
    exit(1);
}

echo json_encode([
    'success' => true,
    'pc_id' => $pcId,
    'command_id' => (int) ($result['command_id'] ?? 0),
    'message' => 'Lock queued — lockscreen should appear within ~5 seconds.',
], JSON_PRETTY_PRINT) . "\n";
