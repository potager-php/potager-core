<?php

namespace Potager\Database;

use PDO;
use Potager\Sillon\Connection;
use Potager\Sillon\QueryBuilder\QueryBuilderHandler;
use Potager\Sillon\QueryBuilder\Raw;
use Potager\Sillon\Schema\SchemaBuilder;
use RuntimeException;

/**
 * Class Database
 *
 * Core service managing the active database connection and query builder instances.
 */
class Database
{
    protected PDO $pdo;

    protected Connection $connection;

    /**
     * Database constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->initializeConnection($config);
    }

    /**
     * Initializes the Sillon Connection and retrieves the underlying PDO instance.
     *
     * @param array $config
     * @throws RuntimeException If connection fails.
     */
    private function initializeConnection(array $config): void
    {
        $connectionConfig = $this->buildConnectionConfig($config);

        try {
            $this->connection = Connection::create($connectionConfig['driver'], $connectionConfig);
            $this->pdo = $this->connection->getPdoInstance();
        } catch (\Exception $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Builds the configuration array for Sillon.
     *
     * @param array $config
     * @return array
     */
    private function buildConnectionConfig(array $config): array
    {
        $defaultOptions = [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_EMULATE_PREPARES => false];
        $options = array_replace($defaultOptions, $config['options'] ?? []);

        return [
            'driver' => $config['driver'] ?? 'mysql',
            'host' => $config['host'] ?? 'localhost',
            'database' => $config['database'] ?? null,
            'username' => $config['username'] ?? null,
            'password' => $config['password'] ?? '',
            'charset' => $config['charset'] ?? 'utf8',
            'collation' => $config['collation'] ?? 'utf8_unicode_ci',
            'prefix' => $config['prefix'] ?? '',
            'options' => $options,
        ];
    }

    /**
     * Returns the raw PDO instance.
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Returns the Sillon Connection instance.
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Returns a new query builder instance.
     *
     * @return QueryBuilderHandler
     */
    public function query(): QueryBuilderHandler
    {
        return $this->connection->getQueryBuilder();
    }

    /**
     * Shortcut to create a query builder scoped to a specific table.
     *
     * @param string $table
     * @return QueryBuilderHandler
     */
    public function table(string $table): QueryBuilderHandler
    {
        return $this->query()->table($table);
    }

    /**
     * Returns an instance of the Schema Builder.
     *
     * @return SchemaBuilder
     */
    public function getSchemaBuilder(): SchemaBuilder
    {
        return $this->connection->getSchemaBuilder();
    }

    /**
     * Shortcut to get the Schema Builder.
     *
     * @return SchemaBuilder
     */
    public function schema(): SchemaBuilder
    {
        return $this->getSchemaBuilder();
    }

    /**
     * Execute a Closure within a database transaction.
     *
     * @param \Closure $callback
     * @return mixed
     * @throws \Throwable
     */
    public function transaction(\Closure $callback): mixed
    {
        return $this->query()->transaction($callback);
    }

    /**
     * Start a new database transaction manually.
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit the active database transaction.
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback the active database transaction.
     *
     * @return bool
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Creates a raw SQL expression.
     *
     * @param mixed $value
     * @return Raw
     */
    public function raw(mixed $value): Raw
    {
        return new Raw($value);
    }

    /**
     * Execute a raw SELECT query and return results as objects.
     *
     * @param string $sql
     * @param array $bindings
     * @return array
     */
    public function select(string $sql, array $bindings = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Execute a raw SQL statement with optional bindings.
     *
     * @param string $sql
     * @param array $bindings
     * @return bool
     */
    public function statement(string $sql, array $bindings = []): bool
    {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($bindings);
    }
}
