<?php

namespace Potager\Sillon\Connections;

use PDO;
use Potager\Sillon\Connection;
use Potager\Sillon\QueryBuilder\Adapters\Sqlite as SqliteQueryAdapter;
use Potager\Sillon\Schema\Adapters\Sqlite as SqliteSchemaAdapter;

class SqliteConnection extends Connection
{
    /**
     * Create the SQLite PDO connection.
     *
     * @param array $config
     * @return PDO
     */
    protected function createPdo(array $config): PDO
    {
        $connectionString = 'sqlite:' . $config['database'];
        $options = $config['options'] ?? [];

        $pdo = new PDO($connectionString, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON;');

        return $pdo;
    }

    /**
     * Get the driver name.
     */
    public function getDriver(): string
    {
        return 'sqlite';
    }

    /**
     * Returns the Query Builder adapter instance.
     */
    public function getQueryAdapter(): SqliteQueryAdapter
    {
        return new SqliteQueryAdapter($this);
    }

    /**
     * Returns the Schema Builder adapter instance.
     */
    public function getSchemaAdapter(): SqliteSchemaAdapter
    {
        return new SqliteSchemaAdapter($this);
    }
}
