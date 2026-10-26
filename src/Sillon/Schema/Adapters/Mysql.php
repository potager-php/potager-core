<?php

namespace Potager\Sillon\Schema\Adapters;

use Potager\Sillon\Schema\Column;

class Mysql extends BaseAdapter
{
    /**
     * @var string
     */
    protected string $sanitizer = '`';

    /**
     * Compile a single column definition for MySQL.
     */
    public function compileColumn(Column $column): string
    {
        $sql = $this->wrapSanitizer($column->name) . ' ' . $this->getType($column);

        if ($column->unsigned) {
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

        if ($column->autoIncrement) {
            $sql .= ' AUTO_INCREMENT PRIMARY KEY';
        } elseif ($column->primary) {
            $sql .= ' PRIMARY KEY';
        }

        if ($column->unique) {
            $sql .= ' UNIQUE';
        }

        if ($column->useCurrentOnUpdate) {
            $sql .= ' ON UPDATE CURRENT_TIMESTAMP';
        }

        if ($column->comment !== null) {
            $sql .= " COMMENT '" . addslashes($column->comment) . "'";
        }

        return $sql;
    }

    /**
     * Get MySQL column type.
     */
    public function getType(Column $column): string
    {
        return match ($column->type) {
            'id', 'bigIncrements' => 'BIGINT',
            'increments' => 'INT',
            'string' => 'VARCHAR(' . ($column->parameters['length'] ?? 255) . ')',
            'char' => 'CHAR(' . ($column->parameters['length'] ?? 255) . ')',
            'text' => 'TEXT',
            'mediumText' => 'MEDIUMTEXT',
            'longText' => 'LONGTEXT',
            'integer' => 'INT',
            'tinyInteger' => 'TINYINT',
            'smallInteger' => 'SMALLINT',
            'mediumInteger' => 'MEDIUMINT',
            'bigInteger' => 'BIGINT',
            'boolean' => 'TINYINT(1)',
            'decimal' => 'DECIMAL(' . ($column->parameters['precision'] ?? 8) . ', ' . ($column->parameters['scale'] ?? 2) . ')',
            'float' => 'FLOAT',
            'double' => 'DOUBLE',
            'date' => 'DATE',
            'time' => 'TIME',
            'dateTime' => 'DATETIME',
            'timestamp' => 'TIMESTAMP',
            'json' => 'JSON',
            'uuid' => 'CHAR(36)',
            'enum' => 'ENUM(' . implode(', ', array_map(fn($v) => "'" . addslashes($v) . "'", $column->parameters['allowed'] ?? [])) . ')',
            'binary' => 'BLOB',
            default => strtoupper($column->type),
        };
    }

    protected function supportsUnsigned(): bool
    {
        return true;
    }

    protected function compileTableOptions(): string
    {
        $config = $this->connection->getAdapterConfig();
        $engine = $config['engine'] ?? 'InnoDB';
        $charset = $config['charset'] ?? 'utf8mb4';
        $collation = $config['collation'] ?? 'utf8mb4_unicode_ci';

        return "ENGINE={$engine} DEFAULT CHARSET={$charset} COLLATE={$collation}";
    }

    public function compileAddColumn(string $table, Column $column): string
    {
        $sql = parent::compileAddColumn($table, $column);
        if ($column->first) {
            $sql .= ' FIRST';
        } elseif ($column->after) {
            $sql .= ' AFTER ' . $this->wrapSanitizer($column->after);
        }
        return $sql;
    }

    public function compileModifyColumn(string $table, Column $column): string
    {
        $wrappedTable = $this->wrapSanitizer($this->prefixTable($table));
        $sql = "ALTER TABLE {$wrappedTable} MODIFY COLUMN " . $this->compileColumn($column);
        if ($column->first) {
            $sql .= ' FIRST';
        } elseif ($column->after) {
            $sql .= ' AFTER ' . $this->wrapSanitizer($column->after);
        }
        return $sql;
    }

    public function compileDropIndex(string $table, string $name): string
    {
        $wrappedTable = $this->wrapSanitizer($this->prefixTable($table));
        return "ALTER TABLE {$wrappedTable} DROP INDEX " . $this->wrapSanitizer($name);
    }

    public function compileDropForeign(string $table, string $name): string
    {
        $wrappedTable = $this->wrapSanitizer($this->prefixTable($table));
        return "ALTER TABLE {$wrappedTable} DROP FOREIGN KEY " . $this->wrapSanitizer($name);
    }

    public function compileTableExists(): string
    {
        return "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?";
    }

    public function compileColumnExists(): string
    {
        return "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?";
    }

    public function compileColumnListing(): string
    {
        return "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position";
    }

    public function compileGetAllTables(): string
    {
        return "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'";
    }

    public function compileDropAllTables(array $tables): array
    {
        $statements = ['SET FOREIGN_KEY_CHECKS = 0;'];
        foreach ($tables as $tbl) {
            $statements[] = 'DROP TABLE IF EXISTS ' . $this->wrapSanitizer($tbl);
        }
        $statements[] = 'SET FOREIGN_KEY_CHECKS = 1;';

        return $statements;
    }
}
