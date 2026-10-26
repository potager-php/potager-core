<?php

namespace Potager\Sillon\Schema;

class Column
{
    public string $name;

    public string $type;

    public array $parameters = [];

    public bool $nullable = false;

    public mixed $default = null;

    public bool $hasDefault = false;

    public bool $unsigned = false;

    public bool $autoIncrement = false;

    public bool $primary = false;

    public bool $unique = false;

    public ?string $indexName = null;

    public ?string $comment = null;

    public ?string $after = null;

    public bool $useCurrent = false;

    public bool $useCurrentOnUpdate = false;

    public bool $change = false;

    public bool $first = false;

    /**
     * Column constructor.
     *
     * @param string $name
     * @param string $type
     * @param array $parameters
     */
    public function __construct(string $name, string $type, array $parameters = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->parameters = $parameters;
    }

    /**
     * Mark the column as nullable.
     *
     * @param bool $value
     * @return $this
     */
    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;
        return $this;
    }

    /**
     * Set a default value for the column.
     *
     * @param mixed $value
     * @return $this
     */
    public function default(mixed $value): self
    {
        $this->default = $value;
        $this->hasDefault = true;
        return $this;
    }

    /**
     * Mark integer column as unsigned.
     *
     * @param bool $value
     * @return $this
     */
    public function unsigned(bool $value = true): self
    {
        $this->unsigned = $value;
        return $this;
    }

    /**
     * Mark the column as unique.
     *
     * @param string|null $indexName
     * @return $this
     */
    public function unique(?string $indexName = null): self
    {
        $this->unique = true;
        $this->indexName = $indexName;
        return $this;
    }

    /**
     * Mark the column as primary key.
     *
     * @return $this
     */
    public function primary(): self
    {
        $this->primary = true;
        return $this;
    }

    /**
     * Mark the column as auto-incrementing.
     *
     * @param bool $value
     * @return $this
     */
    public function autoIncrement(bool $value = true): self
    {
        $this->autoIncrement = $value;
        return $this;
    }

    /**
     * Set a comment on the column.
     *
     * @param string $comment
     * @return $this
     */
    public function comment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Place the column after another column (MySQL only).
     *
     * @param string $column
     * @return $this
     */
    public function after(string $column): self
    {
        $this->after = $column;
        return $this;
    }

    /**
     * Set the default value to CURRENT_TIMESTAMP.
     *
     * @return $this
     */
    public function useCurrent(): self
    {
        $this->useCurrent = true;
        $this->default = 'CURRENT_TIMESTAMP';
        $this->hasDefault = true;
        return $this;
    }

    /**
     * Set column to update to CURRENT_TIMESTAMP on update (MySQL).
     *
     * @return $this
     */
    public function useCurrentOnUpdate(): self
    {
        $this->useCurrentOnUpdate = true;
        return $this;
    }

    /**
     * Place the column first in the table (MySQL only).
     *
     * @return $this
     */
    public function first(): self
    {
        $this->first = true;
        return $this;
    }

    /**
     * Mark the column for modification in an ALTER statement.
     *
     * @return $this
     */
    public function change(): self
    {
        $this->change = true;
        return $this;
    }
}

