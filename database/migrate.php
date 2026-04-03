<?php
/**
 * XPLabs - Database Migration Runner
 * 
 * Usage:
 *   php database/migrate.php          # Run all pending migrations
 *   php database/migrate.php --status # Show migration status
 *   php database/migrate.php --reset  # Drop all tables (DANGER!)
 */

$rootDir = dirname(__DIR__);
$config = require $rootDir . '/config/database.php';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=%s', $config['host'], $config['port'], $config['charset']),
        $config['username'],
        $config['password'],
        $config['options'] ?? []
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET {$config['charset']} COLLATE {$config['collation']}");
    $pdo->exec("USE `{$config['database']}`");

    // Create migrations tracking table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS _migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $migrationsDir = __DIR__ . '/migrations';
    $migrationFiles = glob($migrationsDir . '/*.sql');
    sort($migrationFiles);

    // Get already executed migrations
    $executed = $pdo->query("SELECT migration FROM _migrations")->fetchAll(PDO::FETCH_COLUMN);

    $args = $argv;
    array_shift($args); // Remove script name

    // Handle --status
    if (in_array('--status', $args)) {
        echo "Migration Status:\n";
        echo str_repeat('-', 60) . "\n";
        foreach ($migrationFiles as $file) {
            $name = basename($file);
            $status = in_array($name, $executed) ? '✓ Executed' : '○ Pending';
            echo sprintf("%-40s %s\n", $name, $status);
        }
        exit(0);
    }

    // Handle --reset
    if (in_array('--reset', $args)) {
        echo "⚠ DANGER: This will drop ALL tables in the database!\n";
        echo "Type 'yes' to confirm: ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        if ($line !== 'yes') {
            echo "Cancelled.\n";
            exit(1);
        }

        // Disable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Drop all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            if ($table !== '_migrations') {
                $pdo->exec("DROP TABLE `$table`");
                echo "  Dropped: $table\n";
            }
        }

        // Clear migrations table
        $pdo->exec("TRUNCATE TABLE _migrations");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo "Database reset complete.\n";
        exit(0);
    }

    // Run pending migrations
    $ran = 0;
    foreach ($migrationFiles as $file) {
        $name = basename($file);
        if (in_array($name, $executed)) {
            continue;
        }

        echo "Running: $name ... ";
        $sql = file_get_contents($file);

        try {
            // Handle DELIMITER statements and multi-statement SQL
            $sql = preg_replace('/DELIMITER\s+\/\/\s*/i', '', $sql);
            $sql = preg_replace('/\s*DELIMITER\s*;\s*/i', '', $sql);
            $sql = preg_replace('/END\s*\/\s*/i', 'END;', $sql);
            
            // Split on semicolons but preserve BEGIN...END blocks
            $statements = [];
            $current = '';
            $inBlock = 0;
            foreach (explode(';', $sql) as $part) {
                $trimmed = trim($part);
                if (empty($trimmed)) continue;
                
                $opens = substr_count($trimmed, 'BEGIN');
                $closes = substr_count($trimmed, 'END');
                $inBlock += $opens - $closes;
                
                $current .= ($current ? ';' : '') . $trimmed;
                
                if ($inBlock <= 0) {
                    $statements[] = $current;
                    $current = '';
                }
            }
            if ($current) $statements[] = $current;
            
            foreach ($statements as $stmt) {
                if (!empty($stmt)) {
                    $pdo->exec($stmt);
                }
            }

            $pdo->prepare("INSERT INTO _migrations (migration) VALUES (?)")->execute([$name]);
            echo "✓ Done\n";
            $ran++;
        } catch (PDOException $e) {
            echo "✗ FAILED\n";
            echo "  Error: " . $e->getMessage() . "\n";
            echo "  Stopping migrations.\n";
            exit(1);
        }
    }

    if ($ran === 0) {
        echo "No pending migrations.\n";
    } else {
        echo "\n✓ $ran migration(s) completed.\n";
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}