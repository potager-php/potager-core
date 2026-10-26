<?php

namespace Potager\Sillon\Schema\Adapters;

use Potager\Sillon\Schema\Column;

class Pgsql extends BaseAdapter
{
    /**
     * @var string
     */
    protected string $sanitizer = '"';

    /**
     * Compile a single column definition for PostgreSQL.
     */
    public function compileColumn(Column $column): string
    {
        if ($column->autoIncrement) {
            $type = ($column->type === 'id' || $column->type === 'bigIncrements' || $column->type === 'bigInteger')
                ? 'BIGSERIAL PRIMARY KEY'
                : 'SERIAL PRIMARY KEY';

            return $this->wrapSanitizer($column->name) . ' ' . $type;
        }

        return parent::compileColumn($column);
    }

    /**
     * Get PostgreSQL column type.
     */
    public function getType(Column $column): string
    {
        return match ($column->type) {
            'id', 'bigIncrements' => 'BIGSERIAL',
            'increments' => 'SERIAL',
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
            'time' => 'TIME(0) WITHOUT TIME ZONE',
            'dateTime', 'timestamp' => 'TIMESTAMP(0) WITHOUT TIME ZONE',
            'json' => 'JSON',
            'uuid' => 'UUID',
            'enum' => 'VARCHAR(255)',
            'binary' => 'BYTEA',
            default => strtoupper($column->type),
        };
    }

    /**
     * Compile default boolean value for PostgreSQL.
     */
    protected function compileDefault(mixed $value): string
    {
        if (is_bool($value)) {
            return 'DEFAULT ' . ($value ? 'TRUE' : 'FALSE');
        }

        return parent::compileDefault($value);
    }

    /**
     * Compile drop index query for PostgreSQL.
     */
    public function compileDropIndex(string $table, string $name): string
    {
        return 'DROP INDEX ' . $this->wrapSanitizer($name);
    }

    /**
     * Compile drop foreign key query for PostgreSQL.
     */
    public function compileDropForeign(string $table, string $name): string
    {
        $wrappedTable = $this->wrapSanitizer($this->prefixTable($table));
        return "ALTER TABLE {$wrappedTable} DROP CONSTRAINT " . $this->wrapSanitizer($name);
    }

    public function compileGetAllTables(): string
    {
        return "SELECT table_name FROM information_schema.tables WHERE table_schema = CURRENT_SCHEMA() AND table_type = 'BASE TABLE'";
    }

    public function compileDropAllTables(array $tables): array
    {
        return [
            'DROP SCHEMA public CASCADE;',
            'CREATE SCHEMA public;',
        ];
    }
}
