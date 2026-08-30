<?php

namespace Potager\Console\Commands;

use Potager\Console\GeneratorCommand;
use Potager\Console\IO\InputArgument;
use Potager\Console\IO\InputOption;

class MakeCommandCommand extends GeneratorCommand
{
    protected string $name = 'make:command';
    protected string $description = 'Create a new console command class';
    protected string $type = 'Command';

    protected string $helpText = "The make:command command creates a new console command class in app/Commands.\n\nPass the --generator flag to create a generator command that scaffolds files from stubs.\n\nExamples:\n  php farmer make:command SendEmailCommand\n  php farmer make:command MakeRepositoryCommand --generator";

    protected function getOptions(): array
    {
        return [
            InputOption::make('generator')
                ->shortcut('g')
                ->flag()
                ->description('Generate a GeneratorCommand subclass for scaffolding files from stubs'),
        ];
    }

    protected function getStub(): string
    {
        if ($this->input->option('generator')) {
            return __DIR__ . '/../Stubs/command.generator.stub';
        }

        return __DIR__ . '/../Stubs/command.stub';
    }

    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace . '\Commands';
    }

    protected function qualifyClass(string $name): string
    {
        $name = parent::qualifyClass($name);

        if (!str_ends_with($name, 'Command')) {
            $name .= 'Command';
        }

        return $name;
    }

    protected function replacePlaceholders(string $stub, string $name): string
    {
        $className = $this->getClassName($name);
        $signature = $this->deriveSignature($className);
        $type = $this->deriveType($className);
        $pluralType = $this->derivePlural($type);

        return str_replace(
            ['{{signature}}', '{{ signature }}', '{{type}}', '{{ type }}', '{{pluralType}}', '{{ pluralType }}'],
            [$signature, $signature, $type, $type, $pluralType, $pluralType],
            $stub
        );
    }

    private function deriveType(string $className): string
    {
        $type = preg_replace('/Command$/', '', $className);
        $type = preg_replace('/^Make/', '', $type);

        return $type ?: 'Class';
    }

    private function derivePlural(string $type): string
    {
        if (str_ends_with($type, 'y')) {
            return substr($type, 0, -1) . 'ies';
        }

        return $type . 's';
    }

    /**
     * Derive a default command signature from a PascalCase class name.
     *
     * Examples:
     *   GreetUserCommand    → greet:user
     *   MakeServiceCommand  → make:service
     *   SendEmailCommand    → send:email
     *   MigrateCommand      → migrate
     */
    private function deriveSignature(string $className): string
    {
        $name = preg_replace('/Command$/', '', $className);
        $words = preg_split('/(?=[A-Z])/', $name, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_map('strtolower', $words);

        if (count($words) <= 1) {
            return implode('', $words);
        }

        $namespace = array_shift($words);
        return $namespace . ':' . implode('-', $words);
    }
}
