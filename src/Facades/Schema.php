<?php

namespace Potager\Facades;

use Closure;
use Potager\Sillon\Schema\Blueprint;
use Potager\Sillon\Schema\SchemaBuilder;
use Potager\Support\Facades\Facade;

/**
 * Facade for Database Schema operations.
 *
 * Provides static access to the Schema Builder registered in the application container.
 *
 * @method static void create(string $table, Closure $callback)
 * @method static void table(string $table, Closure $callback)
 * @method static void drop(string $table)
 * @method static void dropIfExists(string $table)
 * @method static void rename(string $from, string $to)
 * @method static bool hasTable(string $table)
 * @method static bool hasColumn(string $table, string $column)
 * @method static array getColumnListing(string $table)
 * @method static void dropAllTables()
 *
 * @see \Potager\Sillon\Schema\SchemaBuilder
 */
class Schema extends Facade
{
    /**
     * Get the registered name of the component in the container.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return SchemaBuilder::class;
    }
}
