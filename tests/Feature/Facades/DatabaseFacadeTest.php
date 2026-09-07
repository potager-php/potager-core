<?php

use Potager\Facades\Database;
use Potager\Sillon\Connection;
use Potager\Sillon\QueryBuilder\QueryBuilderHandler;

beforeEach(function () {
    Database::statement('CREATE TABLE IF NOT EXISTS test_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
});

it('provides access to the database connection and PDO via Database facade', function () {
    expect(Database::getConnection())->toBeInstanceOf(Connection::class)
        ->and(Database::getPdo())->toBeInstanceOf(PDO::class);
});

it('creates query builder instances for tables via Database facade', function () {
    $builder = Database::table('users');
    expect($builder)->toBeInstanceOf(QueryBuilderHandler::class);

    $query = Database::query();
    expect($query)->toBeInstanceOf(QueryBuilderHandler::class);
});

it('can perform database operations through the Database facade', function () {
    Database::table('test_items')->insert(['name' => 'Carrot']);

    $item = Database::table('test_items')->where('name', 'Carrot')->first();
    expect($item)->not->toBeNull()
        ->and($item->name)->toBe('Carrot');
});

it('supports transactions directly through Database facade', function () {
    Database::transaction(function () {
        Database::table('test_items')->insert(['name' => 'Tomato']);
    });

    $item = Database::table('test_items')->where('name', 'Tomato')->first();
    expect($item)->not->toBeNull()
        ->and($item->name)->toBe('Tomato');
});

it('rolls back and rethrows when transaction fails via Database facade', function () {
    expect(function () {
        Database::transaction(function () {
            Database::table('test_items')->insert(['name' => 'BadItem']);
            throw new RuntimeException('Transaction aborted');
        });
    })->toThrow(RuntimeException::class, 'Transaction aborted');

    $item = Database::table('test_items')->where('name', 'BadItem')->first();
    expect($item)->toBeNull();
});

it('supports manual transactions via Database facade', function () {
    Database::beginTransaction();
    Database::table('test_items')->insert(['name' => 'ManualRollback']);
    Database::rollBack();

    $item = Database::table('test_items')->where('name', 'ManualRollback')->first();
    expect($item)->toBeNull();

    Database::beginTransaction();
    Database::table('test_items')->insert(['name' => 'ManualCommit']);
    Database::commit();

    $item = Database::table('test_items')->where('name', 'ManualCommit')->first();
    expect($item)->not->toBeNull()
        ->and($item->name)->toBe('ManualCommit');
});

it('creates raw expressions through Database facade', function () {
    $raw = Database::raw('CURRENT_TIMESTAMP');
    expect($raw)->toBeInstanceOf(\Potager\Sillon\QueryBuilder\Raw::class)
        ->and((string) $raw)->toBe('CURRENT_TIMESTAMP');
});

it('executes raw statements and select queries through Database facade', function () {
    $created = Database::statement('CREATE TABLE test_raw (id INTEGER PRIMARY KEY, value TEXT)');
    expect($created)->toBeTrue();

    Database::statement('INSERT INTO test_raw (id, value) VALUES (?, ?)', [1, 'hello']);
    $rows = Database::select('SELECT * FROM test_raw WHERE id = ?', [1]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->value)->toBe('hello');
});

it('enforces SQLite foreign keys by default', function () {
    Database::statement('CREATE TABLE parents (id INTEGER PRIMARY KEY)');
    Database::statement('CREATE TABLE children (id INTEGER PRIMARY KEY, parent_id INTEGER, FOREIGN KEY(parent_id) REFERENCES parents(id) ON DELETE CASCADE)');

    Database::statement('INSERT INTO parents (id) VALUES (1)');
    Database::statement('INSERT INTO children (id, parent_id) VALUES (10, 1)');

    // Deleting parent should cascade delete child
    Database::statement('DELETE FROM parents WHERE id = 1');
    $remainingChildren = Database::select('SELECT * FROM children WHERE id = 10');
    expect($remainingChildren)->toBeEmpty();
});

it('performs batch insert safely and quickly inside a transaction', function () {
    $ids = Database::table('test_items')->insert([
        ['name' => 'Potato'],
        ['name' => 'Radish'],
        ['name' => 'Cucumber'],
    ]);

    expect($ids)->toBeArray()->toHaveCount(3);

    $count = Database::table('test_items')->whereIn('name', ['Potato', 'Radish', 'Cucumber'])->count();
    expect($count)->toBe(3);
});

