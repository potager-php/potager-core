<?php

namespace Potager\Sillon\Schema;

use Closure;
use PDO;
use Potager\Sillon\Connection;
use Potager\Sillon\Schema\Adapters\BaseAdapter as SchemaAdapter;

class SchemaBuilder
{
    /**
     * @var Connection
     */
    protected Connection $connection;

    /**
     * @var PDO
     */
    protected PDO $pdo;

    /**
     * @var SchemaAdapter
     */
    protected SchemaAdapter $adapterInstance;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->pdo = $connection->getPdoInstance();
        $this->adapterInstance = $connection->getSchemaAdapter();
    }

    /**
     * Get the database connection.
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Get the adapter instance.
     */
    public function getAdapter(): SchemaAdapter
    {
        return $this->adapterInstance;
    }

    /**
     * Create a new table on the schema.
     *
     * @param string $table
     * @param Closure(Blueprint): void $callback
     */
    public function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table, BlueprintPurpose::CREATE);
        $callback($blueprint);

        $statements = $this->adapterInstance->compileCreate($blueprint);
        $this->executeStatements($statements);
    }

    /**
     * Modify an existing table on the schema.
     *
     * @param string $table
     * @param Closure(Blueprint): void $callback
     */
    public function table(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table, BlueprintPurpose::ALTER);
        $callback($blueprint);

        $statements = $this->adapterInstance->compileAlter($blueprint);
        $this->executeStatements($statements);
    }

    /**
     * Drop a table from the schema.
     *
     * @param string $table
     */
    public function drop(string $table): void
    {
        $sql = $this->adapterInstance->compileDrop($table);
        $this->pdo->exec($sql);
    }

    /**
     * Drop a table from the schema if it exists.
     *
     * @param string $table
     */
    public function dropIfExists(string $table): void
    {
        $sql = $this->adapterInstance->compileDropIfExists($table);
        $this->pdo->exec($sql);
    }

    /**
     * Rename a table.
     *
     * @param string $from
     * @param string $to
     */
    public function rename(string $from, string $to): void
    {
        $sql = $this->adapterInstance->compileRename($from, $to);
        $this->pdo->exec($sql);
    }

    /**
     * Check if a table exists.
     *
     * @param string $table
     * @return bool
     */
    public function hasTable(string $table): bool
    {
        $sql = $this->adapterInstance->compileTableExists();
        $prefixed = $this->adapterInstance->prefixTable($table);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$prefixed]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Check if a column exists on a table.
     *
     * @param string $table
     * @param string $column
     * @return bool
     */
    public function hasColumn(string $table, string $column): bool
    {
        $sql = $this->adapterInstance->compileColumnExists();
        $prefixed = $this->adapterInstance->prefixTable($table);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$prefixed, $column]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Get the column names for a given table.
     *
     * @param string $table
     * @return array<string>
     */
    public function getColumnListing(string $table): array
    {
        $sql = $this->adapterInstance->compileColumnListing();
        $prefixed = $this->adapterInstance->prefixTable($table);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$prefixed]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get all tables from the database.
     *
     * @return array<string>
     */
    public function getAllTables(): array
    {
        $sql = $this->adapterInstance->compileGetAllTables();
        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Drop all tables from the database.
     */
    public function dropAllTables(): void
    {
        $tables = $this->getAllTables();
        $statements = $this->adapterInstance->compileDropAllTables($tables);
        $this->executeStatements($statements);
    }

    /**
     * Execute an array of SQL statements.
     *
     * @param array<string> $statements
     */
    protected function executeStatements(array $statements): void
    {
        foreach ($statements as $sql) {
            $sql = trim($sql);
            if ($sql !== '') {
                $this->pdo->exec($sql);
            }
        }
    }
}
