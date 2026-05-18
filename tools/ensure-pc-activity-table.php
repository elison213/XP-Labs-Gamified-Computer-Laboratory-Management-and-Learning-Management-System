<?php
/**
 * Ensure pc_activity_events table exists (migration 052).
 * Usage: php tools/ensure-pc-activity-table.php
 */
require_once __DIR__ . '/../lib/Database.php';

use XPLabs\Lib\Database;

$db = Database::getInstance();
if ($db->tableExists('pc_activity_events')) {
    echo "pc_activity_events already exists.\n";
    exit(0);
}

$migration = __DIR__ . '/../database/migrations/052_pc_activity_events.sql';
if (!is_file($migration)) {
    fwrite(STDERR, "Missing migration file: $migration\n");
    exit(1);
}

$sql = file_get_contents($migration);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "Migration file is empty.\n");
    exit(1);
}

$pdo = $db->getConnection();
foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql))) as $statement) {
    if ($statement === '') {
        continue;
    }
    $pdo->exec($statement);
}

if ($db->tableExists('_migrations')) {
    $name = '052_pc_activity_events.sql';
    $exists = $db->fetch('SELECT 1 FROM _migrations WHERE migration = ?', [$name]);
    if (!$exists) {
        $db->insert('_migrations', ['migration' => $name]);
    }
}

echo "pc_activity_events created.\n";
