<?php

namespace Potager\Sillon;

use PDO;
use Potager\Sillon\QueryBuilder\Adapters\BaseAdapter as BaseQueryAdapter;
use Potager\Sillon\QueryBuilder\QueryBuilderHandler;
use Potager\Sillon\Schema\Adapters\BaseAdapter as BaseSchemaAdapter;
use Potager\Sillon\Schema\SchemaBuilder;

abstract class Connection
{
    /**
     * @var array
     */
    protected array $adapterConfig = [];

    /**
     * @var PDO
     */
    protected PDO $pdoInstance;

    /**
     * @var EventHandler
     */
    protected EventHandler $eventHandler;

    /**
     * Connection constructor.
     *
     * @param array $adapterConfig
     */
    public function __construct(array $adapterConfig)
    {
        $this->adapterConfig = $adapterConfig;
        $this->pdoInstance = $this->createPdo($adapterConfig);
        $this->eventHandler = new EventHandler();
    }

    /**
     * Create the underlying PDO instance.
     *
     * @param array $config
     * @return PDO
     */
    abstract protected function createPdo(array $config): PDO;

    /**
     * Get the driver name (e.g. 'sqlite', 'mysql', 'pgsql').
     */
    abstract public function getDriver(): string;

    /**
     * Returns the Query Builder adapter instance for this connection.
     */
    abstract public function getQueryAdapter(): BaseQueryAdapter;

    /**
     * Returns the Schema Builder adapter instance for this connection.
     */
    abstract public function getSchemaAdapter(): BaseSchemaAdapter;

    /**
     * Factory to create the proper Connection subclass.
     *
     * @param string $driver
     * @param array $config
     * @return Connection
     */
    public static function create(string $driver, array $config): Connection
    {
        return ConnectionFactory::create($driver, $config);
    }

    /**
     * Alias of create().
     *
     * @param string $driver
     * @param array $config
     * @return Connection
     */
    public static function make(string $driver, array $config): Connection
    {
        return ConnectionFactory::create($driver, $config);
    }

    /**
     * Registers a custom connection class or resolver for a driver.
     *
     * @param string $driver
     * @param \Closure|class-string<Connection> $resolver
     */
    public static function registerDriver(string $driver, \Closure|string $resolver): void
    {
        ConnectionFactory::register($driver, $resolver);
    }

    /**
     * Resolves the connection class for a driver.
     *
     * @param string $driver
     * @return class-string<Connection>
     * @throws Exception
     */
    public static function resolveConnection(string $driver): string
    {
        return ConnectionFactory::resolveConnectionClass($driver);
    }

    /**
     * Returns an instance of Query Builder.
     */
    public function getQueryBuilder(): QueryBuilderHandler
    {
        return new QueryBuilderHandler($this);
    }

    /**
     * Returns an instance of Schema Builder.
     */
    public function getSchemaBuilder(): SchemaBuilder
    {
        return new SchemaBuilder($this);
    }

    /**
     * Alias for getDriver() for backwards compatibility.
     */
    public function getAdapter(): string
    {
        return $this->getDriver();
    }

    /**
     * @param PDO $pdo
     * @return self
     */
    public function setPdoInstance(PDO $pdo): self
    {
        $this->pdoInstance = $pdo;
        return $this;
    }

    /**
     * @return PDO
     */
    public function getPdoInstance(): PDO
    {
        return $this->pdoInstance;
    }

    /**
     * @param array $adapterConfig
     * @return self
     */
    public function setAdapterConfig(array $adapterConfig): self
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
    public function getEventHandler(): EventHandler
    {
        return $this->eventHandler;
    }
}
