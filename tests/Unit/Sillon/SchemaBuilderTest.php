<?php

use Potager\Sillon\Connection;
use Potager\Sillon\Schema\Blueprint;
use Potager\Sillon\Schema\SchemaBuilder;
use Potager\Sillon\Schema\Adapters\Sqlite;
use Potager\Sillon\Schema\Adapters\Mysql;
use Potager\Sillon\Schema\Adapters\Pgsql;

beforeEach(function () {
    $this->connection = Connection::create('sqlite', [
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'prefix'   => '',
    ]);
    $this->schema = new SchemaBuilder($this->connection);
});

test('it compiles create table correctly for SQLite', function () {
    $adapter = new Sqlite($this->connection);
    $blueprint = new Blueprint('users', 'create');
    $blueprint->id();
    $blueprint->string('email')->unique();
    $blueprint->string('name', 100)->nullable();
    $blueprint->boolean('is_active')->default(true);
    $blueprint->timestamps();

    $statements = $adapter->compileCreate($blueprint);

    expect($statements)->toHaveCount(1);
    expect($statements[0])->toBe(
        'CREATE TABLE "users" ("id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "email" VARCHAR(255) NOT NULL UNIQUE, "name" VARCHAR(100) NULL, "is_active" INTEGER NOT NULL DEFAULT 1, "created_at" DATETIME NULL, "updated_at" DATETIME NULL)'
    );
});

test('it compiles create table with foreign keys and indexes for SQLite', function () {
    $adapter = new Sqlite($this->connection);
    $blueprint = new Blueprint('posts', 'create');
    $blueprint->id();
    $blueprint->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $blueprint->string('title');
    $blueprint->index('title');

    $statements = $adapter->compileCreate($blueprint);

    expect($statements)->toHaveCount(2);
    expect($statements[0])->toContain('CREATE TABLE "posts"');
    expect($statements[0])->toContain('CONSTRAINT "posts_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE');
    expect($statements[1])->toBe('CREATE INDEX "posts_title_index" ON "posts" ("title")');
});

test('it compiles create table correctly for MySQL', function () {
    $adapter = new Mysql($this->connection);
    $blueprint = new Blueprint('articles', 'create');
    $blueprint->id();
    $blueprint->string('title');
    $blueprint->text('content')->nullable();
    $blueprint->unsignedInteger('views')->default(0);

    $statements = $adapter->compileCreate($blueprint);

    expect($statements)->toHaveCount(1);
    expect($statements[0])->toContain('CREATE TABLE `articles`');
    expect($statements[0])->toContain('`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY');
    expect($statements[0])->toContain('`title` VARCHAR(255) NOT NULL');
    expect($statements[0])->toContain('`content` TEXT NULL');
    expect($statements[0])->toContain('`views` INT UNSIGNED NOT NULL DEFAULT 0');
    expect($statements[0])->toContain('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
});

test('it compiles create table correctly for PostgreSQL', function () {
    $adapter = new Pgsql($this->connection);
    $blueprint = new Blueprint('orders', 'create');
    $blueprint->id();
    $blueprint->boolean('is_paid')->default(false);
    $blueprint->uuid('tracking_code');

    $statements = $adapter->compileCreate($blueprint);

    expect($statements)->toHaveCount(1);
    expect($statements[0])->toContain('CREATE TABLE "orders"');
    expect($statements[0])->toContain('"id" BIGSERIAL PRIMARY KEY');
    expect($statements[0])->toContain('"is_paid" BOOLEAN NOT NULL DEFAULT FALSE');
    expect($statements[0])->toContain('"tracking_code" UUID NOT NULL');
});

test('it compiles alter table add, drop and rename column for SQLite', function () {
    $adapter = new Sqlite($this->connection);
    $blueprint = new Blueprint('users', 'alter');
    $blueprint->string('avatar')->nullable();
    $blueprint->dropColumn('bio');
    $blueprint->renameColumn('old_name', 'new_name');
    $blueprint->index('avatar');

    $statements = $adapter->compileAlter($blueprint);

    expect($statements)->toBe([
        'ALTER TABLE "users" ADD COLUMN "avatar" VARCHAR(255) NULL',
        'ALTER TABLE "users" DROP COLUMN "bio"',
        'ALTER TABLE "users" RENAME COLUMN "old_name" TO "new_name"',
        'CREATE INDEX "users_avatar_index" ON "users" ("avatar")',
    ]);
});

test('it compiles drop and rename table queries', function () {
    $adapter = new Sqlite($this->connection);

    expect($adapter->compileDrop('users'))->toBe('DROP TABLE "users"');
    expect($adapter->compileDropIfExists('users'))->toBe('DROP TABLE IF EXISTS "users"');
    expect($adapter->compileRename('users', 'members'))->toBe('ALTER TABLE "users" RENAME TO "members"');
});

test('it compiles schema inspection and dropAll queries across adapters', function () {
    $sqlite = new Sqlite($this->connection);
    $mysql = new Mysql($this->connection);
    $pgsql = new Pgsql($this->connection);

    expect($sqlite->compileTableExists())->toBe("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
    expect($sqlite->compileColumnExists())->toBe("SELECT 1 FROM pragma_table_info(?) WHERE name = ? COLLATE NOCASE");
    expect($sqlite->compileColumnListing())->toBe("SELECT name FROM pragma_table_info(?)");
    expect($sqlite->compileGetAllTables())->toBe("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
    expect($sqlite->compileDropAllTables(['t1']))->toBe([
        'PRAGMA foreign_keys = OFF;',
        'DROP TABLE IF EXISTS "t1"',
        'PRAGMA foreign_keys = ON;',
    ]);

    expect($mysql->compileTableExists())->toBe("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    expect($mysql->compileColumnExists())->toBe("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    expect($mysql->compileGetAllTables())->toBe("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'");
    expect($mysql->compileDropAllTables(['t1']))->toBe([
        'SET FOREIGN_KEY_CHECKS = 0;',
        'DROP TABLE IF EXISTS `t1`',
        'SET FOREIGN_KEY_CHECKS = 1;',
    ]);

    expect($pgsql->compileTableExists())->toBe("SELECT 1 FROM information_schema.tables WHERE table_schema = CURRENT_SCHEMA() AND table_name = ?");
    expect($pgsql->compileColumnExists())->toBe("SELECT 1 FROM information_schema.columns WHERE table_schema = CURRENT_SCHEMA() AND table_name = ? AND column_name = ?");
    expect($pgsql->compileGetAllTables())->toBe("SELECT table_name FROM information_schema.tables WHERE table_schema = CURRENT_SCHEMA() AND table_type = 'BASE TABLE'");
    expect($pgsql->compileDropAllTables(['t1']))->toBe([
        'DROP SCHEMA public CASCADE;',
        'CREATE SCHEMA public;',
    ]);
});

test('it creates and modifies tables in SQLite in-memory database', function () {
    expect($this->schema->hasTable('users'))->toBeFalse();

    $this->schema->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamps();
    });

    expect($this->schema->hasTable('users'))->toBeTrue();
    expect($this->schema->hasColumn('users', 'id'))->toBeTrue();
    expect($this->schema->hasColumn('users', 'name'))->toBeTrue();
    expect($this->schema->hasColumn('users', 'email'))->toBeTrue();
    expect($this->schema->hasColumn('users', 'non_existent'))->toBeFalse();

    $cols = $this->schema->getColumnListing('users');
    expect($cols)->toContain('id', 'name', 'email', 'created_at', 'updated_at');

    // Insert data using query builder
    $qb = $this->connection->getQueryBuilder();
    $qb->table('users')->insert([
        'name'       => 'Theo',
        'email'      => 'theo@example.com',
        'created_at' => '2026-09-07 20:00:00',
        'updated_at' => '2026-09-07 20:00:00',
    ]);

    $user = $qb->table('users')->where('email', 'theo@example.com')->first();
    expect($user->name)->toBe('Theo');

    // Alter table
    $this->schema->table('users', function (Blueprint $table) {
        $table->string('phone')->nullable();
        $table->renameColumn('name', 'full_name');
    });

    expect($this->schema->hasColumn('users', 'phone'))->toBeTrue();
    expect($this->schema->hasColumn('users', 'full_name'))->toBeTrue();
    expect($this->schema->hasColumn('users', 'name'))->toBeFalse();

    // Rename table
    $this->schema->rename('users', 'accounts');
    expect($this->schema->hasTable('accounts'))->toBeTrue();
    expect($this->schema->hasTable('users'))->toBeFalse();

    // Drop table
    $this->schema->dropIfExists('accounts');
    expect($this->schema->hasTable('accounts'))->toBeFalse();
});

test('it supports foreign keys constraint execution in SQLite', function () {
    $this->schema->create('authors', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    $this->schema->create('books', function (Blueprint $table) {
        $table->id();
        $table->foreignId('author_id')->constrained('authors')->cascadeOnDelete();
        $table->string('title');
    });

    expect($this->schema->hasTable('authors'))->toBeTrue();
    expect($this->schema->hasTable('books'))->toBeTrue();
    expect($this->schema->hasColumn('books', 'author_id'))->toBeTrue();

    $qb = $this->connection->getQueryBuilder();
    $qb->table('authors')->insert(['id' => 1, 'name' => 'Victor Hugo']);
    $qb->table('books')->insert(['author_id' => 1, 'title' => 'Les Misérables']);

    $count = $qb->table('books')->count();
    expect($count)->toBe(1);

    // Deleting author should cascade delete book
    $qb->table('authors')->where('id', 1)->delete();
    expect($qb->table('books')->count())->toBe(0);
});

test('it drops all tables cleanly', function () {
    $this->schema->create('table1', function (Blueprint $table) {
        $table->id();
    });
    $this->schema->create('table2', function (Blueprint $table) {
        $table->id();
    });

    expect($this->schema->hasTable('table1'))->toBeTrue();
    expect($this->schema->hasTable('table2'))->toBeTrue();

    $this->schema->dropAllTables();

    expect($this->schema->hasTable('table1'))->toBeFalse();
    expect($this->schema->hasTable('table2'))->toBeFalse();
});

test('it registers and resolves custom connection driver via factory', function () {
    Connection::registerDriver('custom_db', \Potager\Sillon\Connections\SqliteConnection::class);

    expect(\Potager\Sillon\ConnectionFactory::hasDriver('custom_db'))->toBeTrue();
    $conn = Connection::create('custom_db', ['database' => ':memory:']);
    expect($conn)->toBeInstanceOf(\Potager\Sillon\Connections\SqliteConnection::class)
        ->and($conn->getQueryAdapter())->toBeInstanceOf(\Potager\Sillon\QueryBuilder\Adapters\Sqlite::class)
        ->and($conn->getSchemaAdapter())->toBeInstanceOf(\Potager\Sillon\Schema\Adapters\Sqlite::class);
});

test('it handles blueprint purpose enum and guards against invalid operations during create', function () {
    $createBlueprint = new Blueprint('users', \Potager\Sillon\Schema\BlueprintPurpose::CREATE);
    expect($createBlueprint->getPurpose())->toBe(\Potager\Sillon\Schema\BlueprintPurpose::CREATE);
    expect($createBlueprint->isCreating())->toBeTrue();
    expect($createBlueprint->isAltering())->toBeFalse();

    // String backwards compatibility
    $compatBlueprint = new Blueprint('users', 'alter');
    expect($compatBlueprint->getPurpose())->toBe(\Potager\Sillon\Schema\BlueprintPurpose::ALTER);
    expect($compatBlueprint->isAltering())->toBeTrue();
    expect($compatBlueprint->isCreating())->toBeFalse();

    // Calling dropColumn or renameColumn during CREATE must throw LogicException
    expect(fn() => $createBlueprint->dropColumn('name'))
        ->toThrow(LogicException::class, "Cannot drop column while creating table 'users'.");

    expect(fn() => $createBlueprint->renameColumn('old_name', 'new_name'))
        ->toThrow(LogicException::class, "Cannot rename column while creating table 'users'.");

    expect(fn() => $createBlueprint->dropIndex('users_name_index'))
        ->toThrow(LogicException::class, "Cannot drop index while creating table 'users'.");
});

