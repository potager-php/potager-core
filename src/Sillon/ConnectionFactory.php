<?php

namespace Potager\Sillon;

use Closure;
use Potager\Sillon\Connections\MySqlConnection;
use Potager\Sillon\Connections\PgsqlConnection;
use Potager\Sillon\Connections\SqliteConnection;

class ConnectionFactory
{
    /**
     * Custom driver resolvers.
     *
     * @var array<string, Closure|class-string<Connection>>
     */
    protected static array $resolvers = [];

    /**
     * Create a connection instance based on configuration.
     *
     * @param array $config Must contain a 'driver' key.
     * @return Connection
     * @throws Exception If driver is missing or unsupported.
     */
    public function make(array $config): Connection
    {
        $driver = $config['driver'] ?? null;

        if (!$driver) {
            throw new Exception("A database driver must be specified in the configuration.");
        }

        return static::create($driver, $config);
    }

    /**
     * Create a connection instance for a specific driver.
     *
     * @param string $driver
     * @param array $config
     * @return Connection
     * @throws Exception If driver is unsupported.
     */
    public static function create(string $driver, array $config = []): Connection
    {
        $driverKey = strtolower($driver);

        // 1. Check custom registered resolvers
        if (isset(static::$resolvers[$driverKey])) {
            $resolver = static::$resolvers[$driverKey];
            if ($resolver instanceof Closure) {
                return $resolver($config);
            }
            if (is_string($resolver) && class_exists($resolver)) {
                return new $resolver($config);
            }
        }

        // 2. Direct class name support (e.g. 'driver' => MyCustomConnection::class)
        if (class_exists($driver) && is_subclass_of($driver, Connection::class)) {
            return new $driver($config);
        }

        // 3. Built-in drivers
        $class = static::resolveConnectionClass($driver);
        return new $class($config);
    }

    /**
     * Resolve the connection class for a driver without instantiating it.
     *
     * @param string $driver
     * @return class-string<Connection>
     * @throws Exception If driver is unsupported.
     */
    public static function resolveConnectionClass(string $driver): string
    {
        $driverKey = strtolower($driver);

        if (isset(static::$resolvers[$driverKey])) {
            $resolver = static::$resolvers[$driverKey];
            if (is_string($resolver) && class_exists($resolver)) {
                return $resolver;
            }
        }

        if (class_exists($driver) && is_subclass_of($driver, Connection::class)) {
            return $driver;
        }

        return match ($driverKey) {
            'sqlite', 'sqlite3'               => SqliteConnection::class,
            'mysql'                           => MySqlConnection::class,
            'pgsql', 'postgres', 'postgresql' => PgsqlConnection::class,
            default                           => throw new Exception("Unsupported database driver: [{$driver}]."),
        };
    }

    /**
     * Register a custom driver resolver.
     *
     * @param string $driver
     * @param Closure|class-string<Connection> $resolver
     */
    public static function register(string $driver, Closure|string $resolver): void
    {
        static::$resolvers[strtolower($driver)] = $resolver;
    }

    /**
     * Check if a driver is supported.
     *
     * @param string $driver
     * @return bool
     */
    public static function hasDriver(string $driver): bool
    {
        $key = strtolower($driver);
        if (isset(static::$resolvers[$key])) {
            return true;
        }

        return in_array($key, ['sqlite', 'sqlite3', 'mysql', 'pgsql', 'postgres', 'postgresql'], true);
    }
}
