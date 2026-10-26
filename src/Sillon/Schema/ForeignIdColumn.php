<?php

namespace Potager\Sillon\Schema;

class ForeignIdColumn extends Column
{
    protected Blueprint $blueprint;

    protected ?ForeignKey $foreignKey = null;

    /**
     * @param Blueprint $blueprint
     * @param string $name
     */
    public function __construct(Blueprint $blueprint, string $name)
    {
        parent::__construct($name, 'bigInteger');
        $this->unsigned();
        $this->blueprint = $blueprint;
    }

    /**
     * Define the foreign key constraint.
     *
     * @param string|null $table
     * @param string $column
     * @return $this
     */
    public function constrained(?string $table = null, string $column = 'id'): self
    {
        if ($table === null) {
            $table = $this->guessTableName($this->name);
        }

        if ($this->foreignKey === null) {
            $this->foreignKey = $this->blueprint->foreign($this->name)->references($column)->on($table);
        } else {
            $this->foreignKey->references($column)->on($table);
        }

        return $this;
    }

    /**
     * Guess the table name from a foreign key column name.
     * E.g. 'user_id' -> 'users', 'category_id' -> 'categories'
     *
     * @param string $columnName
     * @return string
     */
    protected function guessTableName(string $columnName): string
    {
        if (str_ends_with($columnName, '_id')) {
            $base = substr($columnName, 0, -3);
            return $this->pluralize($base);
        }

        return $this->pluralize($columnName);
    }

    /**
     * Simple English pluralization helper.
     *
     * @param string $word
     * @return string
     */
    protected function pluralize(string $word): string
    {
        if (str_ends_with($word, 'y') && !preg_match('/[aeiou]y$/i', $word)) {
            return substr($word, 0, -1) . 'ies';
        }
        if (str_ends_with($word, 's') || str_ends_with($word, 'x') || str_ends_with($word, 'ch') || str_ends_with($word, 'sh')) {
            return $word . 'es';
        }
        return $word . 's';
    }

    /**
     * Set the referenced table.
     *
     * @param string $table
     * @return $this
     */
    public function on(string $table): self
    {
        if ($this->foreignKey === null) {
            $this->constrained($table);
        } else {
            $this->foreignKey->on($table);
        }

        return $this;
    }

    /**
     * Set the referenced column(s).
     *
     * @param string|array<string> $columns
     * @return $this
     */
    public function references(string|array $columns): self
    {
        if ($this->foreignKey === null) {
            $this->constrained();
        }
        $this->foreignKey->references($columns);

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
        if ($this->foreignKey === null) {
            $this->constrained();
        }
        $this->foreignKey->onDelete($action);

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
        if ($this->foreignKey === null) {
            $this->constrained();
        }
        $this->foreignKey->onUpdate($action);

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
        $this->nullable();
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
     * Get the associated foreign key constraint instance.
     *
     * @return ForeignKey|null
     */
    public function getForeignKey(): ?ForeignKey
    {
        return $this->foreignKey;
    }
}
