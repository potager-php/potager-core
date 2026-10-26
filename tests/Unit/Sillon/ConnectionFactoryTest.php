<?php

use Potager\Sillon\Connection;
use Potager\Sillon\ConnectionFactory;
use Potager\Sillon\Connections\MySqlConnection;
use Potager\Sillon\Connections\PgsqlConnection;
use Potager\Sillon\Connections\SqliteConnection;
use Potager\Sillon\Exception;

it('creates sqlite connection via make and config array', function () {
    $factory = new ConnectionFactory();
    $conn = $factory->make([
        'driver'   => 'sqlite',
        'database' => ':memory:',
    ]);

    expect($conn)->toBeInstanceOf(SqliteConnection::class)
        ->and($conn->getDriver())->toBe('sqlite');
});

it('throws exception if driver key is missing in config', function () {
    $factory = new ConnectionFactory();
    $factory->make(['database' => ':memory:']);
})->throws(Exception::class, 'A database driver must be specified in the configuration.');

it('resolves connection classes correctly', function () {
    expect(ConnectionFactory::resolveConnectionClass('mysql'))->toBe(MySqlConnection::class)
        ->and(ConnectionFactory::resolveConnectionClass('sqlite'))->toBe(SqliteConnection::class)
        ->and(ConnectionFactory::resolveConnectionClass('sqlite3'))->toBe(SqliteConnection::class)
        ->and(ConnectionFactory::resolveConnectionClass('pgsql'))->toBe(PgsqlConnection::class)
        ->and(ConnectionFactory::resolveConnectionClass('postgres'))->toBe(PgsqlConnection::class)
        ->and(ConnectionFactory::resolveConnectionClass('postgresql'))->toBe(PgsqlConnection::class);
});

it('supports direct connection class string', function () {
    $conn = ConnectionFactory::create(SqliteConnection::class, [
        'database' => ':memory:',
    ]);

    expect($conn)->toBeInstanceOf(SqliteConnection::class);
});

it('supports custom closure resolver in ConnectionFactory', function () {
    ConnectionFactory::register('mock_sqlite', function ($config) {
        return new SqliteConnection($config);
    });

    expect(ConnectionFactory::hasDriver('mock_sqlite'))->toBeTrue();

    $conn = ConnectionFactory::create('mock_sqlite', ['database' => ':memory:']);
    expect($conn)->toBeInstanceOf(SqliteConnection::class);
});

it('supports custom class resolver in ConnectionFactory', function () {
    ConnectionFactory::register('alias_sqlite', SqliteConnection::class);

    expect(ConnectionFactory::resolveConnectionClass('alias_sqlite'))->toBe(SqliteConnection::class);

    $conn = ConnectionFactory::create('alias_sqlite', ['database' => ':memory:']);
    expect($conn)->toBeInstanceOf(SqliteConnection::class);
});
