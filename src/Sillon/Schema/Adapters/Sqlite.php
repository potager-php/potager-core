<?php

namespace Potager\Sillon\Schema\Adapters;

use Potager\Sillon\Schema\Column;

class Sqlite extends BaseAdapter
{
    /**
     * @var string
     */
    protected string $sanitizer = '"';

    /**
     * Compile a single column definition for SQLite.
     * SQLite requires INTEGER PRIMARY KEY AUTOINCREMENT for auto-incrementing keys.
     */
    public function compileColumn(Column $column): string
    {
        if ($column->autoIncrement) {
            return $this->wrapSanitizer($column->name) . ' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL';
        }

        return parent::compileColumn($column);
    }

    /**
     * Get the SQLite column type.
     */
    public function getType(Column $column): string
    {
        return match ($column->type) {
            'id', 'bigIncrements', 'increments' => 'INTEGER',
            'string' => 'VARCHAR(' . ($column->parameters['length'] ?? 255) . ')',
            'char' => 'CHAR(' . ($column->parameters['length'] ?? 255) . ')',
            'text', 'mediumText', 'longText', 'json' => 'TEXT',
            'integer', 'tinyInteger', 'smallInteger', 'mediumInteger', 'bigInteger', 'boolean' => 'INTEGER',
            'decimal', 'float', 'double' => 'NUMERIC',
            'date' => 'DATE',
            'time' => 'TIME',
            'dateTime', 'timestamp' => 'DATETIME',
            'uuid' => 'VARCHAR(36)',
            'enum' => 'VARCHAR(255)',
            'binary' => 'BLOB',
            default => strtoupper($column->type),
        };
    }

    /**
     * Compile table exists query for SQLite.
     */
    public function compileTableExists(): string
    {
        return "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?";
    }

    /**
     * Compile column exists query for SQLite.
     */
    public function compileColumnExists(): string
    {
        return "SELECT 1 FROM pragma_table_info(?) WHERE name = ? COLLATE NOCASE";
    }

    /**
     * Compile column listing query for SQLite.
     */
    public function compileColumnListing(): string
    {
        return "SELECT name FROM pragma_table_info(?)";
    }

    /**
     * Compile query to get all user tables for SQLite.
     */
    public function compileGetAllTables(): string
    {
        return "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'";
    }

    /**
     * Compile statements to drop all tables for SQLite.
     *
     * @param array<string> $tables
     * @return array<string>
     */
    public function compileDropAllTables(array $tables): array
    {
        $statements = ['PRAGMA foreign_keys = OFF;'];
        foreach ($tables as $tbl) {
            $statements[] = 'DROP TABLE IF EXISTS ' . $this->wrapSanitizer($tbl);
        }
        $statements[] = 'PRAGMA foreign_keys = ON;';

        return $statements;
    }

    /**
     * Compile drop index query for SQLite.
     */
    public function compileDropIndex(string $table, string $name): string
    {
        return 'DROP INDEX ' . $this->wrapSanitizer($name);
    }
}
