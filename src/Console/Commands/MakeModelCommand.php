<?php

namespace Potager\Console\Commands;

use Potager\Console\GeneratorCommand;

class MakeModelCommand extends GeneratorCommand
{
    protected string $name = 'make:model';
    protected string $description = 'Create a new Limpid model class';
    protected string $type = 'Model';

    protected function getStub(): string
    {
        return __DIR__ . '/../Stubs/model.stub';
    }

    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace . '\Models';
    }
}
