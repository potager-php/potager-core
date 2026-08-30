<?php

namespace Potager\Console\Commands;

use Potager\Console\GeneratorCommand;

class MakePipeCommand extends GeneratorCommand
{
    protected string $name = 'make:pipe';
    protected string $description = 'Create a new console middleware pipe class';
    protected string $type = 'Pipe';

    protected function getStub(): string
    {
        return __DIR__ . '/../Stubs/pipe.stub';
    }

    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace . '\Pipes';
    }

    protected function qualifyClass(string $name): string
    {
        $name = parent::qualifyClass($name);

        if (!str_ends_with($name, 'Pipe')) {
            $name .= 'Pipe';
        }

        return $name;
    }
}
