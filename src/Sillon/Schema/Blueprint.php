<?php

namespace Potager\Sillon\Schema;

use LogicException;

class Blueprint
{
    /**
     * Table name.
     */
    protected string $table;

    /**
     * Blueprint purpose: BlueprintPurpose::CREATE or BlueprintPurpose::ALTER.
     */
    protected BlueprintPurpose $purpose;

    /**
     * Columns defined on the table.
     *
     * @var array<Column>
     */
    protected array $columns = [];

    /**
     * Alter and index commands.
     *
     * @var array<array{type: string, parameters: array}>
     */
    protected array $commands = [];

    /**
     * Foreign key constraints.
     *
     * @var array<ForeignKey>
     */
    protected array $foreignKeys = [];

    /**
     * @param string $table
     * @param BlueprintPurpose|string $purpose
     */
    public function __construct(string $table, BlueprintPurpose|string $purpose = BlueprintPurpose::CREATE)
    {
        $this->table = $table;
        $this->purpose = is_string($purpose)
            ? (BlueprintPurpose::tryFrom(strtolower($purpose)) ?? BlueprintPurpose::CREATE)
            : $purpose;
    }

    /**
     * Get the table name.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the blueprint purpose.
     */
    public function getPurpose(): BlueprintPurpose
    {
        return $this->purpose;
    }

    /**
     * Determine if the blueprint is creating a new table.
     */
    public function isCreating(): bool
    {
        return $this->purpose === BlueprintPurpose::CREATE;
    }

    /**
     * Determine if the blueprint is altering an existing table.
     */
    public function isAltering(): bool
    {
        return $this->purpose === BlueprintPurpose::ALTER;
    }

    /**
     * Guard clause ensuring that an action is only executed in an ALTER context.
     *
     * @param string $action
     * @throws LogicException
     */
    protected function ensureAltering(string $action): void
    {
        if ($this->isCreating()) {
            throw new LogicException("Cannot {$action} while creating table '{$this->table}'.");
        }
    }

    /**
     * Get all column definitions.
     *
     * @return array<Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get all commands (indexes, drops, renames).
     *
     * @return array<array{type: string, parameters: array}>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Get all foreign key constraints.
     *
     * @return array<ForeignKey>
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    /**
     * Add a column instance to the blueprint.
     *
     * @param Column $column
     * @return Column
     */
    public function addColumn(Column $column): Column
    {
        $this->columns[] = $column;
        return $column;
    }

    /**
     * Create an auto-incrementing big integer primary key column.
     *
     * @param string $name
     * @return Column
     */
    public function id(string $name = 'id'): Column
    {
        return $this->bigIncrements($name);
    }

    /**
     * Create an auto-incrementing integer primary key column.
     *
     * @param string $name
     * @return Column
     */
    public function increments(string $name): Column
    {
        $column = new Column($name, 'integer');
        $column->unsigned()->autoIncrement()->primary();
        return $this->addColumn($column);
    }

    /**
     * Create an auto-incrementing big integer primary key column.
     *
     * @param string $name
     * @return Column
     */
    public function bigIncrements(string $name): Column
    {
        $column = new Column($name, 'bigInteger');
        $column->unsigned()->autoIncrement()->primary();
        return $this->addColumn($column);
    }

    /**
     * Create a string (VARCHAR) column.
     *
     * @param string $name
     * @param int $length
     * @return Column
     */
    public function string(string $name, int $length = 255): Column
    {
        return $this->addColumn(new Column($name, 'string', ['length' => $length]));
    }

    /**
     * Create a char column.
     *
     * @param string $name
     * @param int $length
     * @return Column
     */
    public function char(string $name, int $length = 255): Column
    {
        return $this->addColumn(new Column($name, 'char', ['length' => $length]));
    }

    /**
     * Create a text column.
     *
     * @param string $name
     * @return Column
     */
    public function text(string $name): Column
    {
        return $this->addColumn(new Column($name, 'text'));
    }

    /**
     * Create a medium text column.
     *
     * @param string $name
     * @return Column
     */
    public function mediumText(string $name): Column
    {
        return $this->addColumn(new Column($name, 'mediumText'));
    }

    /**
     * Create a long text column.
     *
     * @param string $name
     * @return Column
     */
    public function longText(string $name): Column
    {
        return $this->addColumn(new Column($name, 'longText'));
    }

    /**
     * Create an integer column.
     *
     * @param string $name
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return Column
     */
    public function integer(string $name, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        $column = new Column($name, 'integer');
        if ($autoIncrement) {
            $column->autoIncrement();
        }
        if ($unsigned) {
            $column->unsigned();
        }
        return $this->addColumn($column);
    }

    /**
     * Create a tiny integer column.
     *
     * @param string $name
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return Column
     */
    public function tinyInteger(string $name, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        $column = new Column($name, 'tinyInteger');
        if ($autoIncrement) {
            $column->autoIncrement();
        }
        if ($unsigned) {
            $column->unsigned();
        }
        return $this->addColumn($column);
    }

    /**
     * Create a small integer column.
     *
     * @param string $name
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return Column
     */
    public function smallInteger(string $name, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        $column = new Column($name, 'smallInteger');
        if ($autoIncrement) {
            $column->autoIncrement();
        }
        if ($unsigned) {
            $column->unsigned();
        }
        return $this->addColumn($column);
    }

    /**
     * Create a medium integer column.
     *
     * @param string $name
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return Column
     */
    public function mediumInteger(string $name, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        $column = new Column($name, 'mediumInteger');
        if ($autoIncrement) {
            $column->autoIncrement();
        }
        if ($unsigned) {
            $column->unsigned();
        }
        return $this->addColumn($column);
    }

    /**
     * Create a big integer column.
     *
     * @param string $name
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return Column
     */
    public function bigInteger(string $name, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        $column = new Column($name, 'bigInteger');
        if ($autoIncrement) {
            $column->autoIncrement();
        }
        if ($unsigned) {
            $column->unsigned();
        }
        return $this->addColumn($column);
    }

    /**
     * Create an unsigned integer column.
     *
     * @param string $name
     * @return Column
     */
    public function unsignedInteger(string $name): Column
    {
        return $this->integer($name, false, true);
    }

    /**
     * Create an unsigned big integer column.
     *
     * @param string $name
     * @return Column
     */
    public function unsignedBigInteger(string $name): Column
    {
        return $this->bigInteger($name, false, true);
    }

    /**
     * Create an unsigned tiny integer column.
     *
     * @param string $name
     * @return Column
     */
    public function unsignedTinyInteger(string $name): Column
    {
        return $this->tinyInteger($name, false, true);
    }

    /**
     * Create an unsigned small integer column.
     *
     * @param string $name
     * @return Column
     */
    public function unsignedSmallInteger(string $name): Column
    {
        return $this->smallInteger($name, false, true);
    }

    /**
     * Create a boolean column.
     *
     * @param string $name
     * @return Column
     */
    public function boolean(string $name): Column
    {
        return $this->addColumn(new Column($name, 'boolean'));
    }

    /**
     * Create a decimal column.
     *
     * @param string $name
     * @param int $precision
     * @param int $scale
     * @return Column
     */
    public function decimal(string $name, int $precision = 8, int $scale = 2): Column
    {
        return $this->addColumn(new Column($name, 'decimal', ['precision' => $precision, 'scale' => $scale]));
    }

    /**
     * Create a float column.
     *
     * @param string $name
     * @param int $precision
     * @param int $scale
     * @return Column
     */
    public function float(string $name, int $precision = 8, int $scale = 2): Column
    {
        return $this->addColumn(new Column($name, 'float', ['precision' => $precision, 'scale' => $scale]));
    }

    /**
     * Create a double column.
     *
     * @param string $name
     * @param int $precision
     * @param int $scale
     * @return Column
     */
    public function double(string $name, int $precision = 8, int $scale = 2): Column
    {
        return $this->addColumn(new Column($name, 'double', ['precision' => $precision, 'scale' => $scale]));
    }

    /**
     * Create a date column.
     *
     * @param string $name
     * @return Column
     */
    public function date(string $name): Column
    {
        return $this->addColumn(new Column($name, 'date'));
    }

    /**
     * Create a time column.
     *
     * @param string $name
     * @return Column
     */
    public function time(string $name): Column
    {
        return $this->addColumn(new Column($name, 'time'));
    }

    /**
     * Create a date-time column.
     *
     * @param string $name
     * @return Column
     */
    public function dateTime(string $name): Column
    {
        return $this->addColumn(new Column($name, 'dateTime'));
    }

    /**
     * Create a timestamp column.
     *
     * @param string $name
     * @return Column
     */
    public function timestamp(string $name): Column
    {
        return $this->addColumn(new Column($name, 'timestamp'));
    }

    /**
     * Create created_at and updated_at nullable timestamps.
     */
    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    /**
     * Alias for timestamps().
     */
    public function nullableTimestamps(): void
    {
        $this->timestamps();
    }

    /**
     * Create a JSON column.
     *
     * @param string $name
     * @return Column
     */
    public function json(string $name): Column
    {
        return $this->addColumn(new Column($name, 'json'));
    }

    /**
     * Create a UUID column.
     *
     * @param string $name
     * @return Column
     */
    public function uuid(string $name): Column
    {
        return $this->addColumn(new Column($name, 'uuid'));
    }

    /**
     * Create an ENUM column.
     *
     * @param string $name
     * @param array<string> $allowed
     * @return Column
     */
    public function enum(string $name, array $allowed): Column
    {
        return $this->addColumn(new Column($name, 'enum', ['allowed' => $allowed]));
    }

    /**
     * Create a binary (BLOB) column.
     *
     * @param string $name
     * @return Column
     */
    public function binary(string $name): Column
    {
        return $this->addColumn(new Column($name, 'binary'));
    }

    /**
     * Create a foreign ID column helper.
     *
     * @param string $name
     * @return ForeignIdColumn
     */
    public function foreignId(string $name): ForeignIdColumn
    {
        $column = new ForeignIdColumn($this, $name);
        $this->addColumn($column);
        return $column;
    }

    /**
     * Add a primary key index command.
     *
     * @param string|array<string> $columns
     * @param string|null $name
     * @return self
     */
    public function primary(string|array $columns, ?string $name = null): self
    {
        $this->addCommand('primary', [
            'columns' => (array) $columns,
            'name' => $name ?? $this->createIndexName('primary', (array) $columns),
        ]);
        return $this;
    }

    /**
     * Add a unique index command.
     *
     * @param string|array<string> $columns
     * @param string|null $name
     * @return self
     */
    public function unique(string|array $columns, ?string $name = null): self
    {
        $this->addCommand('unique', [
            'columns' => (array) $columns,
            'name' => $name ?? $this->createIndexName('unique', (array) $columns),
        ]);
        return $this;
    }

    /**
     * Add a basic index command.
     *
     * @param string|array<string> $columns
     * @param string|null $name
     * @return self
     */
    public function index(string|array $columns, ?string $name = null): self
    {
        $this->addCommand('index', [
            'columns' => (array) $columns,
            'name' => $name ?? $this->createIndexName('index', (array) $columns),
        ]);
        return $this;
    }

    /**
     * Define a foreign key constraint.
     *
     * @param string|array<string> $columns
     * @param string|null $name
     * @return ForeignKey
     */
    public function foreign(string|array $columns, ?string $name = null): ForeignKey
    {
        $foreignKey = new ForeignKey($columns);
        if ($name !== null) {
            $foreignKey->name($name);
        } else {
            $foreignKey->name($this->createIndexName('foreign', (array) $columns));
        }

        $this->foreignKeys[] = $foreignKey;
        return $foreignKey;
    }

    /**
     * Drop column(s) command.
     *
     * @param string|array<string> $columns
     * @return self
     */
    public function dropColumn(string|array $columns): self
    {
        $this->ensureAltering('drop column');
        $this->addCommand('dropColumn', ['columns' => (array) $columns]);
        return $this;
    }

    /**
     * Rename a column command.
     *
     * @param string $from
     * @param string $to
     * @return self
     */
    public function renameColumn(string $from, string $to): self
    {
        $this->ensureAltering('rename column');
        $this->addCommand('renameColumn', ['from' => $from, 'to' => $to]);
        return $this;
    }

    /**
     * Drop primary key command.
     *
     * @param string|null $name
     * @return self
     */
    public function dropPrimary(?string $name = null): self
    {
        $this->ensureAltering('drop primary key');
        $this->addCommand('dropPrimary', ['name' => $name]);
        return $this;
    }

    /**
     * Drop unique index command.
     *
     * @param string|array<string> $columnsOrName
     * @return self
     */
    public function dropUnique(string|array $columnsOrName): self
    {
        $this->ensureAltering('drop unique index');
        $name = is_array($columnsOrName)
            ? $this->createIndexName('unique', $columnsOrName)
            : $columnsOrName;

        $this->addCommand('dropUnique', ['name' => $name]);
        return $this;
    }

    /**
     * Drop index command.
     *
     * @param string|array<string> $columnsOrName
     * @return self
     */
    public function dropIndex(string|array $columnsOrName): self
    {
        $this->ensureAltering('drop index');
        $name = is_array($columnsOrName)
            ? $this->createIndexName('index', $columnsOrName)
            : $columnsOrName;

        $this->addCommand('dropIndex', ['name' => $name]);
        return $this;
    }

    /**
     * Drop foreign key constraint command.
     *
     * @param string|array<string> $columnsOrName
     * @return self
     */
    public function dropForeign(string|array $columnsOrName): self
    {
        $this->ensureAltering('drop foreign key');
        $name = is_array($columnsOrName)
            ? $this->createIndexName('foreign', $columnsOrName)
            : $columnsOrName;

        $this->addCommand('dropForeign', ['name' => $name]);
        return $this;
    }

    /**
     * Add a custom command to the blueprint.
     *
     * @param string $type
     * @param array $parameters
     * @return self
     */
    public function addCommand(string $type, array $parameters = []): self
    {
        $this->commands[] = ['type' => $type, 'parameters' => $parameters];
        return $this;
    }

    /**
     * Generate an index/constraint name.
     *
     * @param string $type
     * @param array<string> $columns
     * @return string
     */
    public function createIndexName(string $type, array $columns): string
    {
        $index = strtolower($this->table . '_' . implode('_', $columns) . '_' . $type);
        return preg_replace('/[^a-z0-9_]/', '_', $index);
    }
}
