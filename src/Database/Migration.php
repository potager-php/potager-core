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
     * Access the Schema Builder instance.
     */
    protected function schema(): SchemaBuilder
    {
        return App::make(SchemaBuilder::class);
    }
}
