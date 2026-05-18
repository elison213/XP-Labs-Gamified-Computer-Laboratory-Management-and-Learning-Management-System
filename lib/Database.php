<?php
/**
 * XPLabs - Database PDO Wrapper
 * Singleton pattern for database connections with convenient query methods.
 */

namespace XPLabs\Lib;

class Database
{
    private static ?Database $instance = null;
    private \PDO $connection;
    private array $config;

    private function __construct()
    {
        $this->config = require __DIR__ . '/../config/database.php';
        $this->connect();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect(): void
    {
        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['driver'],
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset']
        );

        try {
            $this->connection = new \PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options'] ?? []
            );
        } catch (\PDOException $e) {
            // #region agent log
            file_put_contents(
                __DIR__ . '/../debug-10ea95.log',
                json_encode([
                    'sessionId' => '10ea95',
                    'runId' => 'initial',
                    'hypothesisId' => 'H6',
                    'location' => 'lib/Database.php:connect',
                    'message' => 'pdo_connect_failed',
                    'data' => [
                        'driver' => $this->config['driver'] ?? null,
                        'host' => $this->config['host'] ?? null,
                        'port' => $this->config['port'] ?? null,
                        'database' => $this->config['database'] ?? null,
                        'charset' => $this->config['charset'] ?? null,
                        'exception_code' => $e->getCode(),
                        'exception_message' => $e->getMessage(),
                    ],
                    'timestamp' => (int) round(microtime(true) * 1000),
                ], JSON_UNESCAPED_SLASHES) . PHP_EOL,
                FILE_APPEND
            );
            // #endregion
            throw $e;
        }
    }

    public function getConnection(): \PDO
    {
        return $this->connection;
    }

    /**
     * Execute a query and return the statement.
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row.
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Fetch all rows.
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Fetch a single column value.
     */
    public function fetchOne(string $sql, array $params = []): mixed
    {
        $result = $this->query($sql, $params)->fetchColumn();
        return $result !== false ? $result : null;
    }

    /**
     * Insert a row and return the last insert ID.
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_map(fn($col) => "`$col`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
        $this->query($sql, array_values($data));
        return (int) $this->connection->lastInsertId();
    }

    /**
     * Update rows and return affected count.
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($col) => "`$col` = ?", array_keys($data)));
        $sql = "UPDATE `$table` SET $set WHERE $where";
        $stmt = $this->query($sql, array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    /**
     * Delete rows and return affected count.
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $stmt = $this->query("DELETE FROM `$table` WHERE $where", $params);
        return $stmt->rowCount();
    }

    /**
     * Begin a transaction.
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit a transaction.
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Rollback a transaction.
     */
    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }

    /**
     * Get the last insert ID.
     */
    public function lastInsertId(): int
    {
        return (int) $this->connection->lastInsertId();
    }

    /**
     * Check if a table exists.
     */
    public function tableExists(string $table): bool
    {
        $result = $this->fetchOne(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
            [$this->config['database'], $table]
        );
        return (int) $result > 0;
    }
}