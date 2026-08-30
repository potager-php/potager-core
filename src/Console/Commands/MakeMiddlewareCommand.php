<?php

namespace Potager\Console\Commands;

use Potager\Console\GeneratorCommand;

class MakeMiddlewareCommand extends GeneratorCommand
{
    protected string $name = 'make:middleware';
    protected string $description = 'Create a new middleware class';
    protected string $type = 'Middleware';

    protected function getStub(): string
    {
        return __DIR__ . '/../Stubs/middleware.stub';
    }

    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace . '\Middleware';
    }

    protected function qualifyClass(string $name): string
    {
        $name = parent::qualifyClass($name);

        if (!str_ends_with($name, 'Middleware')) {
            $name .= 'Middleware';
        }

        return $name;
    }
}
