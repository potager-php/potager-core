<?php

namespace Potager\Facades;

use PDO;
use Potager\Database\Database as DatabaseService;
use Potager\Sillon\Connection;
use Potager\Sillon\QueryBuilder\QueryBuilderHandler;
use Potager\Sillon\QueryBuilder\Raw;
use Potager\Support\Facades\Facade;

/**
 * Facade for Database and Query Builder operations.
 *
 * Provides static access to the Database service registered in the application container.
 *
 * @method static QueryBuilderHandler table(string $table)
 * @method static QueryBuilderHandler query()
 * @method static PDO getPdo()
 * @method static Connection getConnection()
 * @method static mixed transaction(\Closure $callback)
 * @method static bool beginTransaction()
 * @method static bool commit()
 * @method static bool rollBack()
 * @method static Raw raw(mixed $value)
 * @method static array select(string $sql, array $bindings = [])
 * @method static bool statement(string $sql, array $bindings = [])
 *
 * @see \Potager\Database\Database
 */
class Database extends Facade
{
    /**
     * Get the registered name of the component in the container.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return DatabaseService::class;
    }
}
