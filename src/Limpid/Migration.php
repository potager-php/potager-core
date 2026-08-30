<?php

namespace Potager\Limpid;

abstract class Migration
{
    abstract public string $tableName;

    abstract public function up();

    public function down()
    {
        return;
    }
}
