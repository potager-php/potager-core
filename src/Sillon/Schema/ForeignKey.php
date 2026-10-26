<?php

namespace Potager\Sillon\Schema;

class ForeignKey
{
    /**
     * Local column(s) on the table.
     *
     * @var array<string>
     */
    public array $columns = [];

    /**
     * Referenced column(s) on the foreign table.
     *
     * @var array<string>
     */
    public array $references = ['id'];

    /**
     * Target referenced table name.
     */
    public ?string $onTable = null;

    /**
     * ON DELETE action (e.g. CASCADE, SET NULL, RESTRICT, NO ACTION).
     */
    public ?string $onDelete = null;

    /**
     * ON UPDATE action (e.g. CASCADE, SET NULL, RESTRICT, NO ACTION).
     */
    public ?string $onUpdate = null;

    /**
     * Custom constraint name.
     */
    public ?string $name = null;

    /**
     * @param string|array<string> $columns
     */
    public function __construct(string|array $columns)
    {
        $this->columns = (array) $columns;
    }

    /**
     * Set the referenced column(s) on the foreign table.
     *
     * @param string|array<string> $columns
     * @return $this
     */
    public function references(string|array $columns): self
    {
        $this->references = (array) $columns;
        return $this;
    }

    /**
     * Set the referenced table.
     *
     * @param string $table
     * @return $this
     */
    public function on(string $table): self
    {
        $this->onTable = $table;
        return $this;
    }

    /**
     * Set the ON DELETE action.
     *
     * @param string $action
     * @return $this
     */
    public function onDelete(string $action): self
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    /**
     * Set the ON UPDATE action.
     *
     * @param string $action
     * @return $this
     */
    public function onUpdate(string $action): self
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    /**
     * Cascade on delete.
     *
     * @return $this
     */
    public function cascadeOnDelete(): self
    {
        return $this->onDelete('cascade');
    }

    /**
     * Set null on delete.
     *
     * @return $this
     */
    public function nullOnDelete(): self
    {
        return $this->onDelete('set null');
    }

    /**
     * Restrict on delete.
     *
     * @return $this
     */
    public function restrictOnDelete(): self
    {
        return $this->onDelete('restrict');
    }

    /**
     * Cascade on update.
     *
     * @return $this
     */
    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate('cascade');
    }

    /**
     * Restrict on update.
     *
     * @return $this
     */
    public function restrictOnUpdate(): self
    {
        return $this->onUpdate('restrict');
    }

    /**
     * Set explicit constraint name.
     *
     * @param string $name
     * @return $this
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}
