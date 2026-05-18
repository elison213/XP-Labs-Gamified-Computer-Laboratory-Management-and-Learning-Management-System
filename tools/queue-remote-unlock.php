<?php
/**
 * Queue a remote unlock command for a lab PC (CLI test / admin helper).
 *
 * Usage (from project root, XAMPP PHP):
 *   C:\xampp\php\php.exe tools\queue-remote-unlock.php --hostname=LAB-PC-01
 *   C:\xampp\php\php.exe tools\queue-remote-unlock.php --pc-id=3
 *   C:\xampp\php\php.exe tools\queue-remote-unlock.php --list
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

$list = in_array('--list', $argv, true);
$hostname = arg('hostname');
$pcId = (int) (arg('pc-id') ?? '0');

$db = Database::getInstance();

if ($list) {
    $rows = $db->fetchAll(
        "SELECT id, hostname, status, last_seen, station_id, floor_id
         FROM lab_pcs
         ORDER BY last_seen DESC
         LIMIT 20"
    );
    echo "Recent lab PCs:\n";
    foreach ($rows as $r) {
        echo sprintf(
            "  id=%d  hostname=%s  status=%s  last_seen=%s\n",
            (int) $r['id'],
            (string) ($r['hostname'] ?? ''),
            (string) ($r['status'] ?? ''),
            (string) ($r['last_seen'] ?? '')
        );
    }
    exit(0);
}

if ($pcId <= 0 && $hostname) {
    $row = $db->fetch('SELECT id FROM lab_pcs WHERE hostname = ? LIMIT 1', [$hostname]);
    if (!$row) {
        fwrite(STDERR, "No PC with hostname: {$hostname}\n");
        exit(1);
    }
    $pcId = (int) $row['id'];
}

if ($pcId <= 0) {
    fwrite(STDERR, "Usage: php tools/queue-remote-unlock.php --hostname=NAME | --pc-id=N | --list\n");
    exit(1);
}

$pc = $db->fetch('SELECT id, hostname, status FROM lab_pcs WHERE id = ?', [$pcId]);
if (!$pc) {
    fwrite(STDERR, "PC id {$pcId} not found.\n");
    exit(1);
}

$svc = new PCService();
$result = $svc->queueCommand($pcId, 0, 'unlock', ['source' => 'cli_test'], 300);

if (!($result['success'] ?? false)) {
    fwrite(STDERR, 'Failed: ' . ($result['error'] ?? 'unknown') . "\n");
    exit(1);
}

echo json_encode([
    'success' => true,
    'pc_id' => $pcId,
    'hostname' => $pc['hostname'] ?? '',
    'command_id' => (int) ($result['command_id'] ?? 0),
    'message' => 'Unlock queued. Agent should pick it up within ~5 seconds.',
], JSON_PRETTY_PRINT) . "\n";
