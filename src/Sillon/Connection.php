<?php

namespace Potager\Sillon;

use Potager\Sillon\QueryBuilder\QueryBuilderHandler;

class Connection
{
    /**
     * @var string
     */
    protected $adapter;

    /**
     * @var array
     */
    protected $adapterConfig;

    /**
     * @var \PDO
     */
    protected $pdoInstance;

    /**
     * @var EventHandler
     */
    protected $eventHandler;

    /**
     * Driver mapping for connection and query builder adapters.
     *
     * @var array<string, array{connection: class-string, query: class-string}>
     */
    protected static array $adapterMap = [
        'mysql'      => [
            'connection' => \Potager\Sillon\ConnectionAdapters\Mysql::class,
            'query'      => \Potager\Sillon\QueryBuilder\Adapters\Mysql::class,
        ],
        'pgsql'      => [
            'connection' => \Potager\Sillon\ConnectionAdapters\Pgsql::class,
            'query'      => \Potager\Sillon\QueryBuilder\Adapters\Pgsql::class,
        ],
        'postgres'   => [
            'connection' => \Potager\Sillon\ConnectionAdapters\Pgsql::class,
            'query'      => \Potager\Sillon\QueryBuilder\Adapters\Pgsql::class,
        ],
        'postgresql' => [
            'connection' => \Potager\Sillon\ConnectionAdapters\Pgsql::class,
            'query'      => \Potager\Sillon\QueryBuilder\Adapters\Pgsql::class,
        ],
        'sqlite'     => [
            'connection' => \Potager\Sillon\ConnectionAdapters\Sqlite::class,
            'query'      => \Potager\Sillon\QueryBuilder\Adapters\Sqlite::class,
        ],
        'sqlite3'    => [
            'connection' => \Potager\Sillon\ConnectionAdapters\Sqlite::class,
            'query'      => \Potager\Sillon\QueryBuilder\Adapters\Sqlite::class,
        ],
    ];

    /**
     * @param string $adapter
     * @param array  $adapterConfig
     */
    public function __construct(string $adapter, array $adapterConfig)
    {
        $this->setAdapter($adapter)->setAdapterConfig($adapterConfig)->connect();

        // Create event dependency
        $this->eventHandler = new EventHandler();
    }

    /**
     * Returns an instance of Query Builder
     */
    public function getQueryBuilder(): QueryBuilderHandler
    {
        return new QueryBuilderHandler($this);
    }

    /**
     * Registers a custom adapter or driver mapping.
     *
     * @param string $driver
     * @param class-string $connectionAdapter
     * @param class-string $queryAdapter
     */
    public static function registerAdapter(string $driver, string $connectionAdapter, string $queryAdapter): void
    {
        static::$adapterMap[strtolower($driver)] = [
            'connection' => $connectionAdapter,
            'query'      => $queryAdapter,
        ];
    }

    /**
     * Resolves the connection adapter class for a given driver.
     *
     * @param string $driver
     * @return class-string<\Potager\Sillon\ConnectionAdapters\BaseAdapter>
     * @throws Exception If adapter is unsupported.
     */
    public static function resolveConnectionAdapter(string $driver): string
    {
        $key = strtolower($driver);
        if (isset(static::$adapterMap[$key])) {
            return static::$adapterMap[$key]['connection'];
        }

        $fallback = '\\Potager\\Sillon\\ConnectionAdapters\\' . ucfirst($key);
        if (class_exists($fallback)) {
            return $fallback;
        }

        throw new Exception("Unsupported database driver: [{$driver}].");
    }

    /**
     * Resolves the query builder adapter class for a given driver.
     *
     * @param string $driver
     * @return class-string<\Potager\Sillon\QueryBuilder\Adapters\BaseAdapter>
     * @throws Exception If adapter is unsupported.
     */
    public static function resolveQueryAdapter(string $driver): string
    {
        $key = strtolower($driver);
        if (isset(static::$adapterMap[$key])) {
            return static::$adapterMap[$key]['query'];
        }

        $fallback = '\\Potager\\Sillon\\QueryBuilder\\Adapters\\' . ucfirst($driver);
        if (class_exists($fallback)) {
            return $fallback;
        }

        throw new Exception("Unsupported query builder adapter for driver: [{$driver}].");
    }

    /**
     * Create the connection adapter
     */
    protected function connect()
    {
        $adapterClass = static::resolveConnectionAdapter($this->adapter);

        /** @var \Potager\Sillon\ConnectionAdapters\BaseAdapter $adapterInstance */
        $adapterInstance = new $adapterClass();
        $pdo = $adapterInstance->connect($this->adapterConfig);

        $this->setPdoInstance($pdo);
    }

    /**
     * @param \PDO $pdo
     *
     * @return $this
     */
    public function setPdoInstance(\PDO $pdo)
    {
        $this->pdoInstance = $pdo;
        return $this;
    }

    /**
     * @return \PDO
     */
    public function getPdoInstance()
    {
        return $this->pdoInstance;
    }

    /**
     * @param $adapter
     *
     * @return $this
     */
    public function setAdapter($adapter)
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * @return string
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @param array $adapterConfig
     *
     * @return $this
     */
    public function setAdapterConfig(array $adapterConfig)
    {
        $this->adapterConfig = $adapterConfig;
        return $this;
    }

    /**
     * @return array
     */
    public function getAdapterConfig(): array
    {
        return $this->adapterConfig;
    }

    /**
     * @return EventHandler
     */
    public function getEventHandler()
    {
        return $this->eventHandler;
    }
}
