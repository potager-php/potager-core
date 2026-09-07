<?php

use Potager\Sillon\Connection;
use Potager\Sillon\Exception;

beforeEach(function () {
    // Set up SQLite in-memory connection
    $config = [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => 'cb_',
    ];

    $connection = new Connection('sqlite', $config);
    $this->builder = $connection->getQueryBuilder();

    $pdo = $connection->getPdoInstance();

    // Create tables with prefix 'cb_'
    $pdo->exec("
        CREATE TABLE cb_my_table (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT
        );
    ");

    $pdo->exec("
        CREATE TABLE cb_test (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT
        );
    ");

    // Insert sample data for raw query test
    $stmt = $pdo->prepare("INSERT INTO cb_my_table (id, name) VALUES (?, ?)");
    $stmt->execute([5, 'usman']);
});

it('can run a raw query with bindings', function () {
    $query = 'select * from cb_my_table where id = ? and name = ?';
    $bindings = [5, 'usman'];

    $result = $this->builder->query($query, $bindings)->get();

    expect($result)->toHaveCount(1);
    expect($result[0])->toBeInstanceOf(stdClass::class);
    expect($result[0]->id)->toBe(5);
    expect($result[0]->name)->toBe('usman');
});

it('insert query returns id for insert', function () {
    $id = $this->builder->table('test')->insert([
        'id' => 5,
        'name' => 'usman',
    ]);

    expect((int) $id)->toBeInt()->toBeGreaterThan(0);

    // Verify the inserted row
    $stmt = $this->builder->getConnection()->getPdoInstance()->prepare('SELECT * FROM cb_test WHERE id = ?');
    $stmt->execute([5]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    expect($row)->not->toBeFalse();
    expect($row['name'])->toBe('usman');
});

it('throws exception when table not specified', function () {
    $this->builder->where('a', 'b')->get();
})->throws(Exception::class, 'No table specified.', 3);

it('commits transaction when closure succeeds', function () {
    $this->builder->transaction(function ($t) {
        $t->table('test')->insert(['id' => 10, 'name' => 'Alice']);
    });

    $row = $this->builder->table('test')->where('id', 10)->first();
    expect($row)->not->toBeNull()
        ->and($row->name)->toBe('Alice');
});

it('rolls back and rethrows exception when transaction fails', function () {
    expect(function () {
        $this->builder->transaction(function ($t) {
            $t->table('test')->insert(['id' => 20, 'name' => 'Bob']);
            throw new RuntimeException('Intentional failure');
        });
    })->toThrow(RuntimeException::class, 'Intentional failure');

    $row = $this->builder->table('test')->where('id', 20)->first();
    expect($row)->toBeNull();
});

it('supports manual rollback in transaction without rethrowing', function () {
    $this->builder->transaction(function ($t) {
        $t->table('test')->insert(['id' => 30, 'name' => 'Charlie']);
        $t->rollback();
    });

    $row = $this->builder->table('test')->where('id', 30)->first();
    expect($row)->toBeNull();
});

it('resolves driver aliases correctly in Connection', function () {
    expect(Connection::resolveConnectionAdapter('postgres'))->toBe(\Potager\Sillon\ConnectionAdapters\Pgsql::class)
        ->and(Connection::resolveConnectionAdapter('postgresql'))->toBe(\Potager\Sillon\ConnectionAdapters\Pgsql::class)
        ->and(Connection::resolveQueryAdapter('postgres'))->toBe(\Potager\Sillon\QueryBuilder\Adapters\Pgsql::class)
        ->and(Connection::resolveQueryAdapter('postgresql'))->toBe(\Potager\Sillon\QueryBuilder\Adapters\Pgsql::class)
        ->and(Connection::resolveConnectionAdapter('sqlite3'))->toBe(\Potager\Sillon\ConnectionAdapters\Sqlite::class)
        ->and(Connection::resolveQueryAdapter('sqlite3'))->toBe(\Potager\Sillon\QueryBuilder\Adapters\Sqlite::class);
});

it('throws exception when unsupported driver is requested', function () {
    Connection::resolveConnectionAdapter('unknown_driver');
})->throws(Exception::class, 'Unsupported database driver: [unknown_driver].');

