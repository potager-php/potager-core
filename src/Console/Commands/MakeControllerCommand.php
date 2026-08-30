<?php

namespace Potager\Console\Commands;

use Potager\Console\GeneratorCommand;

class MakeControllerCommand extends GeneratorCommand
{
    protected string $name = 'make:controller';
    protected string $description = 'Create a new controller class';
    protected string $type = 'Controller';

    protected function getStub(): string
    {
        return __DIR__ . '/../Stubs/controller.stub';
    }

    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace . '\Controllers';
    }

    protected function qualifyClass(string $name): string
    {
        $name = parent::qualifyClass($name);

        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        return $name;
    }
}
