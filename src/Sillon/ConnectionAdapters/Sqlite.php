<?php
namespace Potager\Sillon\ConnectionAdapters;

class Sqlite extends BaseAdapter
{
    /**
     * @param $config
     *
     * @return mixed
     */
    public function doConnect($config)
    {
        $connectionString = 'sqlite:' . $config['database'];
        $pdo = new \PDO($connectionString, null, null, $config['options']);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        return $pdo;
    }
}
