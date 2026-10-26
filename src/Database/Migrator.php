<?php

namespace Potager\Database;

use Closure;
use Potager\Sillon\Schema\Blueprint;
use Potager\Sillon\Schema\SchemaBuilder;
use RuntimeException;

/**
 * Core migration engine orchestrating DDL operations and tracking migration batches.
 */
class Migrator
{
    /**
     * Database service instance.
     */
    protected Database $database;

    /**
     * Schema builder instance.
     */
    protected SchemaBuilder $schema;

    /**
     * Name of the migrations tracking table.
     */
    protected string $table = 'potager_migrations';

    /**
     * Optional callback for real-time progress notifications.
     *
     * Signature: function (string $event, string $migration, float $duration = 0.0): void
     */
    protected ?Closure $outputCallback = null;

    /**
     * Migrator constructor.
     */
    public function __construct(Database $database)
    {
        $this->database = $database;
        $this->schema = $database->getSchemaBuilder();
    }

    /**
     * Get the underlying Database instance.
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * Get the Schema Builder instance.
     */
    public function getSchema(): SchemaBuilder
    {
        return $this->schema;
    }

    /**
     * Get the tracking table name.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Set the tracking table name.
     */
    public function setTable(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Set output progress callback.
     */
    public function setOutputCallback(?Closure $callback): self
    {
        $this->outputCallback = $callback;
        return $this;
    }

    /**
     * Emit a notification to the output callback.
     */
    protected function notify(string $event, string $migration, float $duration = 0.0): void
    {
        if ($this->outputCallback !== null) {
            ($this->outputCallback)($event, $migration, $duration);
        }
    }

    /**
     * Check if the migrations repository table exists.
     */
    public function repositoryExists(): bool
    {
        return $this->schema->hasTable($this->table);
    }

    /**
     * Create the migrations repository table if it does not already exist.
     */
    public function createRepositoryIfNotExists(): void
    {
        if ($this->repositoryExists()) {
            return;
        }

        $this->schema->create($this->table, function (Blueprint $table) {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
            $table->dateTime('created_at')->nullable();
        });
    }

    /**
     * Get list of migration names that have already been executed.
     *
     * @return array<string>
     */
    public function getRan(): array
    {
        if (!$this->repositoryExists()) {
            return [];
        }

        $rows = $this->database->table($this->table)
            ->select('migration')
            ->orderBy('id', 'asc')
            ->get();

        return array_map(function ($row) {
            return is_object($row) ? $row->migration : $row['migration'];
        }, $rows);
    }

    /**
     * Get the latest batch number recorded in the repository.
     */
    public function getLastBatchNumber(): int
    {
        if (!$this->repositoryExists()) {
            return 0;
        }

        $rows = $this->database->table($this->table)
            ->select('batch')
            ->orderBy('batch', 'desc')
            ->limit(1)
            ->get();

        if (empty($rows)) {
            return 0;
        }

        $first = $rows[0];
        return (int) (is_object($first) ? $first->batch : $first['batch']);
    }

    /**
     * Get the next batch number.
     */
    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * Get all migration files from a given directory, sorted chronologically.
     *
     * @param string $path
     * @return array<string>
     */
    public function getMigrationFiles(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $files = glob(rtrim($path, '/') . '/*_*.php') ?: [];
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Extract migration name from file path without extension.
     */
    public function getMigrationName(string $filePath): string
    {
        return basename($filePath, '.php');
    }

    /**
     * Get pending migration files that have not yet been executed.
     *
     * @param string $path
     * @return array<string>
     */
    public function getPendingMigrations(string $path): array
    {
        $files = $this->getMigrationFiles($path);
        $ran = $this->getRan();

        return array_values(array_filter($files, function ($file) use ($ran) {
            return !in_array($this->getMigrationName($file), $ran, true);
        }));
    }

    /**
     * Resolve and instantiate the Migration class from a file.
     *
     * @param string $filePath
     * @return Migration
     * @throws RuntimeException
     */
    public function resolveMigration(string $filePath): Migration
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Migration file not found: [{$filePath}].");
        }

        $preDeclaredClasses = get_declared_classes();

        $migration = require $filePath;

        if ($migration instanceof Migration) {
            $migration->setSchema($this->schema);
            return $migration;
        }

        // Support for named migration classes
        $postDeclaredClasses = get_declared_classes();
        $newClasses = array_diff($postDeclaredClasses, $preDeclaredClasses);

        foreach (array_reverse($newClasses) as $class) {
            if (is_subclass_of($class, Migration::class)) {
                $instance = new $class();
                $instance->setSchema($this->schema);
                return $instance;
            }
        }

        throw new RuntimeException("Migration file [{$filePath}] does not return or define a Migration instance.");
    }

    /**
     * Run all pending migrations in the given directory.
     *
     * @param string $path
     * @param array $options
     * @return array<string> Array of executed migration names.
     */
    public function run(string $path, array $options = []): array
    {
        $this->createRepositoryIfNotExists();

        $pending = $this->getPendingMigrations($path);

        if (empty($pending)) {
            return [];
        }

        $batch = $this->getNextBatchNumber();
        $ran = [];

        foreach ($pending as $file) {
            $name = $this->getMigrationName($file);

            $this->notify('running', $name);

            $start = microtime(true);

            $migration = $this->resolveMigration($file);
            $migration->up();

            $duration = round((microtime(true) - $start) * 1000, 2);

            $this->database->table($this->table)->insert([
                'migration'  => $name,
                'batch'      => $batch,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->notify('migrated', $name, $duration);
            $ran[] = $name;
        }

        return $ran;
    }

    /**
     * Rollback the last migration batch or a specified number of steps.
     *
     * @param string $path
     * @param array{step?: int|null, batch?: int|null} $options
     * @return array<string> Array of rolled back migration names.
     */
    public function rollback(string $path, array $options = []): array
    {
        if (!$this->repositoryExists()) {
            return [];
        }

        $query = $this->database->table($this->table);

        if (!empty($options['step'])) {
            $query->orderBy('id', 'desc')->limit((int) $options['step']);
        } elseif (!empty($options['batch'])) {
            $query->where('batch', '=', (int) $options['batch'])->orderBy('id', 'desc');
        } else {
            $lastBatch = $this->getLastBatchNumber();
            if ($lastBatch === 0) {
                return [];
            }
            $query->where('batch', '=', $lastBatch)->orderBy('id', 'desc');
        }

        $records = $query->get();

        if (empty($records)) {
            return [];
        }

        $files = $this->getMigrationFiles($path);
        $fileMap = [];
        foreach ($files as $file) {
            $fileMap[$this->getMigrationName($file)] = $file;
        }

        $rolledBack = [];

        foreach ($records as $record) {
            $id = is_object($record) ? $record->id : $record['id'];
            $name = is_object($record) ? $record->migration : $record['migration'];

            if (!isset($fileMap[$name])) {
                throw new RuntimeException("Migration file for [{$name}] not found in [{$path}].");
            }

            $this->notify('reverting', $name);

            $start = microtime(true);

            $migration = $this->resolveMigration($fileMap[$name]);
            $migration->down();

            $duration = round((microtime(true) - $start) * 1000, 2);

            $this->database->table($this->table)->where('id', '=', $id)->delete();

            $this->notify('reverted', $name, $duration);
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * Rollback all migrations.
     *
     * @param string $path
     * @return array<string>
     */
    public function reset(string $path): array
    {
        return $this->rollback($path, ['step' => PHP_INT_MAX]);
    }

    /**
     * Drop all tables from the connected database.
     */
    public function wipe(): void
    {
        $this->schema->dropAllTables();
    }

    /**
     * Drop all tables and re-run all migrations from scratch.
     *
     * @param string $path
     * @param array $options
     * @return array<string>
     */
    public function fresh(string $path, array $options = []): array
    {
        $this->wipe();
        return $this->run($path, $options);
    }
}
