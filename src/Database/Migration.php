<?php

namespace Potager\Database;

use Potager\App;
use Potager\Sillon\Schema\SchemaBuilder;

abstract class Migration
{
    /**
     * Optional table name for legacy/simple migrations.
     */
    public ?string $tableName = null;

    /**
     * Run the migrations.
     */
    abstract public function up();

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        return;
    }

    /**
     * Schema Builder instance.
     */
    protected ?SchemaBuilder $schema = null;

    /**
     * Set the Schema Builder instance.
     */
    public function setSchema(SchemaBuilder $schema): static
    {
        $this->schema = $schema;
        return $this;
    }

    /**
     * Access the Schema Builder instance.
     */
    protected function schema(): SchemaBuilder
    {
        return $this->schema ?? App::make(SchemaBuilder::class);
    }
}
