<?php

namespace Potager\Sillon\Schema\Adapters;

use Potager\Sillon\Connection;
use Potager\Sillon\QueryBuilder\Raw;
use Potager\Sillon\Schema\Blueprint;
use Potager\Sillon\Schema\Column;
use Potager\Sillon\Schema\ForeignKey;

abstract class BaseAdapter
{
    /**
     * @var Connection
     */
    protected Connection $connection;

    /**
     * Identifier quote sanitizer (` or ").
     *
     * @var string
     */
    protected string $sanitizer = '"';

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Wrap values with adapter's sanitizer.
     *
     * @param mixed $value
     * @return string
     */
    public function wrapSanitizer(mixed $value): string
    {
        if ($value instanceof Raw) {
            return (string) $value;
        }

        $valueArr = explode('.', (string) $value, 2);

        foreach ($valueArr as $key => $subValue) {
            $valueArr[$key] = trim($subValue) === '*' ? $subValue : $this->sanitizer . $subValue . $this->sanitizer;
        }

        return implode('.', $valueArr);
    }

    /**
     * Get the table name with any configured prefix.
     */
    public function prefixTable(string $table): string
    {
        $prefix = $this->connection->getAdapterConfig()['prefix'] ?? '';
        return $prefix . $table;
    }

    /**
     * Compile a CREATE TABLE blueprint into an array of SQL statements.
     * The first statement creates the table; subsequent statements create any secondary indexes.
     *
     * @param Blueprint $blueprint
     * @return array<string>
     */
    public function compileCreate(Blueprint $blueprint): array
    {
        $table = $this->wrapSanitizer($this->prefixTable($blueprint->getTable()));
        $definitions = [];

        // Compile column definitions
        foreach ($blueprint->getColumns() as $column) {
            $definitions[] = $this->compileColumn($column);
        }

        // Compile table-level constraints (composite primary key, unique constraints, foreign keys)
        $constraints = $this->compileTableConstraints($blueprint);
        if (!empty($constraints)) {
            $definitions = array_merge($definitions, $constraints);
        }

        $sql = "CREATE TABLE {$table} (" . implode(', ', $definitions) . ')';

        $options = $this->compileTableOptions();
        if ($options !== '') {
            $sql .= ' ' . $options;
        }

        $statements = [$sql];

        // Compile any secondary indexes (CREATE INDEX / CREATE UNIQUE INDEX)
        foreach ($blueprint->getCommands() as $command) {
            if ($command['type'] === 'index') {
                $statements[] = $this->compileCreateIndex($blueprint->getTable(), $command['parameters']['name'], $command['parameters']['columns'], false);
            }
        }

        return $statements;
    }

    /**
     * Compile table-level constraints for CREATE TABLE.
     *
     * @param Blueprint $blueprint
     * @return array<string>
     */
    protected function compileTableConstraints(Blueprint $blueprint): array
    {
        $constraints = [];

        // Primary keys defined via command
        foreach ($blueprint->getCommands() as $command) {
            if ($command['type'] === 'primary') {
                $cols = implode(', ', array_map(fn($c) => $this->wrapSanitizer($c), $command['parameters']['columns']));
                $constraints[] = "PRIMARY KEY ({$cols})";
            } elseif ($command['type'] === 'unique') {
                $cols = implode(', ', array_map(fn($c) => $this->wrapSanitizer($c), $command['parameters']['columns']));
                $name = $this->wrapSanitizer($command['parameters']['name']);
                $constraints[] = "CONSTRAINT {$name} UNIQUE ({$cols})";
            }
        }

        // Foreign keys
        foreach ($blueprint->getForeignKeys() as $fk) {
            $cols = implode(', ', array_map(fn($c) => $this->wrapSanitizer($c), $fk->columns));
            $refTable = $this->wrapSanitizer($this->prefixTable($fk->onTable));
            $refCols = implode(', ', array_map(fn($c) => $this->wrapSanitizer($c), $fk->references));

            $sql = '';
            if ($fk->name) {
                $sql .= 'CONSTRAINT ' . $this->wrapSanitizer($fk->name) . ' ';
            }
            $sql .= "FOREIGN KEY ({$cols}) REFERENCES {$refTable} ({$refCols})";

            if ($fk->onDelete) {
                $sql .= " ON DELETE {$fk->onDelete}";
            }
            if ($fk->onUpdate) {
                $sql .= " ON UPDATE {$fk->onUpdate}";
            }

            $constraints[] = $sql;
        }

        return $constraints;
    }

    /**
     * Compile table options (e.g. engine, charset, collation).
     */
    protected function compileTableOptions(): string
    {
        return '';
    }

    /**
     * Compile a single column definition.
     */
    public function compileColumn(Column $column): string
    {
        $sql = $this->wrapSanitizer($column->name) . ' ' . $this->getType($column);

        if ($column->unsigned && $this->supportsUnsigned()) {
            $sql .= ' UNSIGNED';
        }

        if ($column->nullable) {
            $sql .= ' NULL';
        } else {
            $sql .= ' NOT NULL';
        }

        if ($column->hasDefault) {
            $sql .= ' ' . $this->compileDefault($column->default);
        }

        if ($column->autoIncrement && $this->supportsInlineAutoIncrement()) {
            $sql .= ' ' . $this->getAutoIncrementKeyword();
        }

        if ($column->primary && $this->supportsInlinePrimary()) {
            $sql .= ' PRIMARY KEY';
        }

        if ($column->unique && $this->supportsInlineUnique()) {
            $sql .= ' UNIQUE';
        }

        return $sql;
    }

    /**
     * Compile a default value.
     */
    protected function compileDefault(mixed $value): string
    {
        if ($value === null) {
            return 'DEFAULT NULL';
        }

        if (is_bool($value)) {
            return 'DEFAULT ' . ($value ? '1' : '0');
        }

        if (is_int($value) || is_float($value)) {
            return "DEFAULT {$value}";
        }

        if (is_string($value) && strtoupper($value) === 'CURRENT_TIMESTAMP') {
            return 'DEFAULT CURRENT_TIMESTAMP';
        }

        $escaped = str_replace("'", "''", (string) $value);
        return "DEFAULT '{$escaped}'";
    }

    /**
     * Get the SQL type for a column.
     */
    public function getType(Column $column): string
    {
        return match ($column->type) {
            'id', 'bigIncrements' => 'BIGINT',
            'increments' => 'INTEGER',
            'string' => 'VARCHAR(' . ($column->parameters['length'] ?? 255) . ')',
            'char' => 'CHAR(' . ($column->parameters['length'] ?? 255) . ')',
            'text', 'mediumText', 'longText' => 'TEXT',
            'integer', 'mediumInteger' => 'INTEGER',
            'tinyInteger', 'smallInteger' => 'SMALLINT',
            'bigInteger' => 'BIGINT',
            'boolean' => 'BOOLEAN',
            'decimal' => 'NUMERIC(' . ($column->parameters['precision'] ?? 8) . ', ' . ($column->parameters['scale'] ?? 2) . ')',
            'float' => 'REAL',
            'double' => 'DOUBLE PRECISION',
            'date' => 'DATE',
            'time' => 'TIME',
            'dateTime', 'timestamp' => 'TIMESTAMP',
            'json' => 'TEXT',
            'uuid' => 'VARCHAR(36)',
            'enum' => 'VARCHAR(255)',
            'binary' => 'BLOB',
            default => strtoupper($column->type),
        };
    }

    protected function supportsUnsigned(): bool
    {
        return false;
    }

    protected function supportsInlineAutoIncrement(): bool
    {
        return false;
    }

    protected function getAutoIncrementKeyword(): string
    {
        return 'AUTO_INCREMENT';
    }

    protected function supportsInlinePrimary(): bool
    {
        return true;
    }

    protected function supportsInlineUnique(): bool
    {
        return true;
    }

    /**
     * Compile an ALTER TABLE blueprint into an array of SQL statements.
     *
     * @param Blueprint $blueprint
     * @return array<string>
     */
    public function compileAlter(Blueprint $blueprint): array
    {
        $statements = [];
        $table = $this->wrapSanitizer($this->prefixTable($blueprint->getTable()));

        // Add or modify columns
        foreach ($blueprint->getColumns() as $column) {
            if ($column->change) {
                $statements[] = $this->compileModifyColumn($blueprint->getTable(), $column);
            } else {
                $statements[] = $this->compileAddColumn($blueprint->getTable(), $column);
            }
        }

        // Alter commands
        foreach ($blueprint->getCommands() as $command) {
            switch ($command['type']) {
                case 'dropColumn':
                    foreach ($command['parameters']['columns'] as $col) {
                        $statements[] = "ALTER TABLE {$table} DROP COLUMN " . $this->wrapSanitizer($col);
                    }
                    break;
                case 'renameColumn':
                    $from = $this->wrapSanitizer($command['parameters']['from']);
                    $to = $this->wrapSanitizer($command['parameters']['to']);
                    $statements[] = "ALTER TABLE {$table} RENAME COLUMN {$from} TO {$to}";
                    break;
                case 'index':
                    $statements[] = $this->compileCreateIndex($blueprint->getTable(), $command['parameters']['name'], $command['parameters']['columns'], false);
                    break;
                case 'unique':
                    $statements[] = $this->compileCreateIndex($blueprint->getTable(), $command['parameters']['name'], $command['parameters']['columns'], true);
                    break;
                case 'dropIndex':
                case 'dropUnique':
                    $statements[] = $this->compileDropIndex($blueprint->getTable(), $command['parameters']['name']);
                    break;
                case 'dropForeign':
                    $statements[] = $this->compileDropForeign($blueprint->getTable(), $command['parameters']['name']);
                    break;
            }
        }

        // Added foreign keys in alter
        foreach ($blueprint->getForeignKeys() as $fk) {
            $statements[] = $this->compileAddForeignKey($blueprint->getTable(), $fk);
        }

        return $statements;
    }

    /**
     * Compile ADD COLUMN statement.
     */
    public function compileAddColumn(string $table, Column $column): string
    {
        $wrappedTable = $this->wrapSanitizer($this->prefixTable($table));
        return "ALTER TABLE {$wrappedTable} ADD COLUMN " . $this->compileColumn($column);
    }

    /**
     * Compile MODIFY COLUMN statement.
     */
    public function compileModifyColumn(string $table, Column $column): string
    {
        $wrappedTable = $this->wrapSanitizer($this->prefixTable($table));
        return "ALTER TABLE {$wrappedTable} ALTER COLUMN " . $this->wrapSanitizer($column->name) . " TYPE " . $this->getType($column);
    }

    /**
     * Compile CREATE INDEX statement.
     */
    public function compileCreateIndex(string $table, string $name, array $columns, bool $unique = false): string
    {
        $wrappedTable = $this->wrapSanitizer($this->prefixTable($table));
        $wrappedName = $this->wrapSanitizer($name);
        $cols = implode(', ', array_map(fn($c) => $this->wrapSanitizer($c), $columns));
        $type = $unique ? 'UNIQUE INDEX' : 'INDEX';

        return "CREATE {$type} {$wrappedName} ON {$wrappedTable} ({$cols})";
    }

    /**
     * Compile DROP INDEX statement.
     */
    public function compileDropIndex(string $table, string $name): string
    {
        return "DROP INDEX " . $this->wrapSanitizer($name);
    }

    /**
     * Compile ADD FOREIGN KEY statement.
     */
    public function compileAddForeignKey(string $table, ForeignKey $fk): string
    {
        $wrappedTable = $this->wrapSanitizer($this->prefixTable($table));
        $cols = implode(', ', array_map(fn($c) => $this->wrapSanitizer($c), $fk->columns));
        $refTable = $this->wrapSanitizer($this->prefixTable($fk->onTable));
        $refCols = implode(', ', array_map(fn($c) => $this->wrapSanitizer($c), $fk->references));

        $sql = "ALTER TABLE {$wrappedTable} ADD ";
        if ($fk->name) {
            $sql .= 'CONSTRAINT ' . $this->wrapSanitizer($fk->name) . ' ';
        }
        $sql .= "FOREIGN KEY ({$cols}) REFERENCES {$refTable} ({$refCols})";

        if ($fk->onDelete) {
            $sql .= " ON DELETE {$fk->onDelete}";
        }
        if ($fk->onUpdate) {
            $sql .= " ON UPDATE {$fk->onUpdate}";
        }

        return $sql;
    }

    /**
     * Compile DROP FOREIGN KEY statement.
     */
    public function compileDropForeign(string $table, string $name): string
    {
        $wrappedTable = $this->wrapSanitizer($this->prefixTable($table));
        return "ALTER TABLE {$wrappedTable} DROP CONSTRAINT " . $this->wrapSanitizer($name);
    }

    /**
     * Compile DROP TABLE statement.
     */
    public function compileDrop(string $table): string
    {
        return 'DROP TABLE ' . $this->wrapSanitizer($this->prefixTable($table));
    }

    /**
     * Compile DROP TABLE IF EXISTS statement.
     */
    public function compileDropIfExists(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $this->wrapSanitizer($this->prefixTable($table));
    }

    /**
     * Compile RENAME TABLE statement.
     */
    public function compileRename(string $from, string $to): string
    {
        return 'ALTER TABLE ' . $this->wrapSanitizer($this->prefixTable($from)) . ' RENAME TO ' . $this->wrapSanitizer($this->prefixTable($to));
    }

    /**
     * Compile query to check if a table exists.
     */
    public function compileTableExists(): string
    {
        return "SELECT 1 FROM information_schema.tables WHERE table_schema = CURRENT_SCHEMA() AND table_name = ?";
    }

    /**
     * Compile query to check if a column exists.
     */
    public function compileColumnExists(): string
    {
        return "SELECT 1 FROM information_schema.columns WHERE table_schema = CURRENT_SCHEMA() AND table_name = ? AND column_name = ?";
    }

    /**
     * Compile query to list column names of a table.
     */
    public function compileColumnListing(): string
    {
        return "SELECT column_name FROM information_schema.columns WHERE table_schema = CURRENT_SCHEMA() AND table_name = ? ORDER BY ordinal_position";
    }

    /**
     * Compile query to list all table names.
     */
    public function compileGetAllTables(): string
    {
        return "SELECT table_name FROM information_schema.tables WHERE table_schema = CURRENT_SCHEMA() AND table_type = 'BASE TABLE'";
    }

    /**
     * Compile statements to drop all tables.
     *
     * @param array<string> $tables
     * @return array<string>
     */
    public function compileDropAllTables(array $tables): array
    {
        $statements = [];
        foreach ($tables as $tbl) {
            $statements[] = 'DROP TABLE IF EXISTS ' . $this->wrapSanitizer($tbl) . ' CASCADE';
        }
        return $statements;
    }
}
