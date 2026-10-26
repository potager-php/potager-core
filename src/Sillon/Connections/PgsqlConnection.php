<?php

namespace Potager\Sillon\Connections;

use PDO;
use Potager\Sillon\Connection;
use Potager\Sillon\QueryBuilder\Adapters\Pgsql as PgsqlQueryAdapter;
use Potager\Sillon\Schema\Adapters\Pgsql as PgsqlSchemaAdapter;

class PgsqlConnection extends Connection
{
    /**
     * Create the PostgreSQL PDO connection.
     *
     * @param array $config
     * @return PDO
     */
    protected function createPdo(array $config): PDO
    {
        $connectionString = "pgsql:host={$config['host']};dbname={$config['database']}";

        if (isset($config['port'])) {
            $connectionString .= ";port={$config['port']}";
        }

        $pdo = new PDO(
            $connectionString,
            $config['username'] ?? null,
            $config['password'] ?? '',
            $config['options'] ?? []
        );

        if (isset($config['charset'])) {
            $pdo->prepare("SET NAMES '{$config['charset']}'")->execute();
        }

        if (isset($config['schema'])) {
            $pdo->prepare("SET search_path TO '{$config['schema']}'")->execute();
        }

        return $pdo;
    }

    /**
     * Get the driver name.
     */
    public function getDriver(): string
    {
        return 'pgsql';
    }

    /**
     * Returns the Query Builder adapter instance.
     */
    public function getQueryAdapter(): PgsqlQueryAdapter
    {
        return new PgsqlQueryAdapter($this);
    }

    /**
     * Returns the Schema Builder adapter instance.
     */
    public function getSchemaAdapter(): PgsqlSchemaAdapter
    {
        return new PgsqlSchemaAdapter($this);
    }
}
