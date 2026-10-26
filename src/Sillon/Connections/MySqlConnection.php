<?php

namespace Potager\Sillon\Connections;

use PDO;
use Potager\Sillon\Connection;
use Potager\Sillon\QueryBuilder\Adapters\Mysql as MysqlQueryAdapter;
use Potager\Sillon\Schema\Adapters\Mysql as MysqlSchemaAdapter;

class MySqlConnection extends Connection
{
    /**
     * Create the MySQL PDO connection.
     *
     * @param array $config
     * @return PDO
     */
    protected function createPdo(array $config): PDO
    {
        $connectionString = "mysql:dbname={$config['database']}";

        if (isset($config['host'])) {
            $connectionString .= ";host={$config['host']}";
        }

        if (isset($config['port'])) {
            $connectionString .= ";port={$config['port']}";
        }

        if (isset($config['unix_socket'])) {
            $connectionString .= ";unix_socket={$config['unix_socket']}";
        }

        if (isset($config['charset'])) {
            $connectionString .= ";charset={$config['charset']}";
        }

        return new PDO(
            $connectionString,
            $config['username'] ?? null,
            $config['password'] ?? '',
            $config['options'] ?? []
        );
    }

    /**
     * Get the driver name.
     */
    public function getDriver(): string
    {
        return 'mysql';
    }

    /**
     * Returns the Query Builder adapter instance.
     */
    public function getQueryAdapter(): MysqlQueryAdapter
    {
        return new MysqlQueryAdapter($this);
    }

    /**
     * Returns the Schema Builder adapter instance.
     */
    public function getSchemaAdapter(): MysqlSchemaAdapter
    {
        return new MysqlSchemaAdapter($this);
    }
}
