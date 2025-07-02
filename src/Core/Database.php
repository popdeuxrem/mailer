<?php

declare(strict_types=1);

namespace EmailPlatform\Core;

use PDO;
use PDOException;
use Exception;

/**
 * Advanced Database Layer with Query Builder
 * 
 * Provides database abstraction with query building,
 * connection pooling, and migration support.
 */
class Database
{
    private PDO $connection;
    private array $config;
    private array $queryLog = [];
    private bool $enableQueryLog = false;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    /**
     * Establish database connection
     */
    private function connect(): void
    {
        try {
            $dsn = $this->buildDsn();
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true,
            ];

            $this->connection = new PDO(
                $dsn,
                $this->config['user'] ?? null,
                $this->config['pass'] ?? null,
                $options
            );

            // Set timezone for MySQL
            if ($this->config['type'] === 'mysql') {
                $this->connection->exec("SET time_zone = '+00:00'");
            }

        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Build DSN string based on database type
     */
    private function buildDsn(): string
    {
        return match ($this->config['type']) {
            'mysql' => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->config['host'],
                $this->config['port'],
                $this->config['name']
            ),
            'sqlite' => 'sqlite:' . $this->config['path'],
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $this->config['host'],
                $this->config['port'],
                $this->config['name']
            ),
            default => throw new Exception("Unsupported database type: {$this->config['type']}")
        };
    }

    /**
     * Execute a query with parameters
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $this->logQuery($sql, $params);

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Query execution failed: " . $e->getMessage() . " SQL: " . $sql);
        }
    }

    /**
     * Fetch all results
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Fetch single row
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Insert record and return ID
     */
    public function insert(string $table, array $data): string
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->query($sql, $data);
        return $this->connection->lastInsertId();
    }

    /**
     * Update records
     */
    public function update(string $table, array $data, array $where): int
    {
        $setClauses = array_map(fn($col) => "$col = :$col", array_keys($data));
        $whereClauses = array_map(fn($col) => "$col = :where_$col", array_keys($where));

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $setClauses),
            implode(' AND ', $whereClauses)
        );

        // Prefix where parameters to avoid conflicts
        $whereParams = [];
        foreach ($where as $key => $value) {
            $whereParams["where_$key"] = $value;
        }

        $params = array_merge($data, $whereParams);
        $stmt = $this->query($sql, $params);
        
        return $stmt->rowCount();
    }

    /**
     * Delete records
     */
    public function delete(string $table, array $where): int
    {
        $whereClauses = array_map(fn($col) => "$col = :$col", array_keys($where));

        $sql = sprintf(
            "DELETE FROM %s WHERE %s",
            $table,
            implode(' AND ', $whereClauses)
        );

        $stmt = $this->query($sql, $where);
        return $stmt->rowCount();
    }

    /**
     * Start transaction
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->connection->rollback();
    }

    /**
     * Execute transaction with callback
     */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Check if table exists
     */
    public function tableExists(string $table): bool
    {
        $sql = match ($this->config['type']) {
            'mysql' => "SHOW TABLES LIKE :table",
            'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name=:table",
            'pgsql' => "SELECT tablename FROM pg_tables WHERE tablename=:table",
            default => throw new Exception("Unsupported database type for table check")
        };

        $result = $this->fetchOne($sql, ['table' => $table]);
        return !empty($result);
    }

    /**
     * Get table schema
     */
    public function getTableSchema(string $table): array
    {
        $sql = match ($this->config['type']) {
            'mysql' => "DESCRIBE $table",
            'sqlite' => "PRAGMA table_info($table)",
            'pgsql' => "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = :table",
            default => throw new Exception("Unsupported database type for schema")
        };

        return $this->fetchAll($sql, $this->config['type'] === 'pgsql' ? ['table' => $table] : []);
    }

    /**
     * Run migration files
     */
    public function migrate(string $migrationsPath): void
    {
        // Create migrations table if it doesn't exist
        $this->createMigrationsTable();

        $files = glob($migrationsPath . '/*.sql');
        sort($files);

        foreach ($files as $file) {
            $migration = basename($file, '.sql');
            
            if (!$this->migrationExists($migration)) {
                $sql = file_get_contents($file);
                
                $this->transaction(function() use ($sql, $migration) {
                    $this->connection->exec($sql);
                    $this->recordMigration($migration);
                });

                echo "Executed migration: $migration\n";
            }
        }
    }

    /**
     * Create migrations tracking table
     */
    private function createMigrationsTable(): void
    {
        $sql = match ($this->config['type']) {
            'mysql' => "
                CREATE TABLE IF NOT EXISTS migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_migration (migration)
                ) ENGINE=InnoDB
            ",
            'sqlite' => "
                CREATE TABLE IF NOT EXISTS migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration TEXT NOT NULL UNIQUE,
                    executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ",
            'pgsql' => "
                CREATE TABLE IF NOT EXISTS migrations (
                    id SERIAL PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ",
            default => throw new Exception("Unsupported database type for migrations")
        };

        $this->connection->exec($sql);
    }

    /**
     * Check if migration exists
     */
    private function migrationExists(string $migration): bool
    {
        $result = $this->fetchOne(
            "SELECT 1 FROM migrations WHERE migration = :migration",
            ['migration' => $migration]
        );
        return !empty($result);
    }

    /**
     * Record executed migration
     */
    private function recordMigration(string $migration): void
    {
        $this->insert('migrations', ['migration' => $migration]);
    }

    /**
     * Enable query logging
     */
    public function enableQueryLog(): void
    {
        $this->enableQueryLog = true;
    }

    /**
     * Get query log
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Log executed query
     */
    private function logQuery(string $sql, array $params): void
    {
        if ($this->enableQueryLog) {
            $this->queryLog[] = [
                'sql' => $sql,
                'params' => $params,
                'time' => microtime(true)
            ];
        }
    }

    /**
     * Get raw PDO connection
     */
    public function getPdo(): PDO
    {
        return $this->connection;
    }
}