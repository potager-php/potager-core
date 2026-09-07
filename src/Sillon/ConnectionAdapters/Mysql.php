<?php
namespace Potager\Sillon\ConnectionAdapters;

class Mysql extends BaseAdapter
{
    /**
     * @param $config
     *
     * @return mixed
     */
    protected function doConnect($config)
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

        $connection = new \PDO($connectionString, $config['username'], $config['password'], $config['options']);

        return $connection;
    }
}
