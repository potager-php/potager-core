<?php

namespace Potager\Console;

use Potager\App;
use Potager\Console\IO\InputArgument;
use Potager\Console\IO\InputOption;

/**
 * Base class for generator commands that create PHP classes from stubs.
 *
 * Implements the Template Method pattern to standardize class qualification,
 * sub-namespace resolution, directory creation, placeholder replacement,
 * and duplicate file protection with the `--force` option.
 */
abstract class GeneratorCommand extends Command
{
    /**
     * The type of class being generated (e.g. "Controller", "Model", "Command").
     *
     * @var string
     */
    protected string $type = 'Class';

    /**
     * Get the stub file for the generator.
     *
     * @return string The absolute path to the stub file.
     */
    abstract protected function getStub(): string;

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace The root application namespace (e.g. "App").
     * @return string
     */
    abstract protected function getDefaultNamespace(string $rootNamespace): string;

    /**
     * Get the human-readable type of class being generated.
     *
     * @return string
     */
    protected function type(): string
    {
        return $this->type;
    }

    /**
     * Get the root namespace for the application.
     *
     * @return string
     */
    protected function rootNamespace(): string
    {
        return 'App';
    }

    /**
     * Returns the base input definition for generator commands.
     *
     * Automatically includes the class name argument, any additional arguments from
     * getArguments(), the --force flag, and any additional options from getOptions().
     *
     * @return array<\Potager\Console\IO\InputArgument|\Potager\Console\IO\InputOption>
     */
    protected function getInputDefinition(): array
    {
        return [
            InputArgument::make('name')
                ->description("The name of the {$this->type()} class to create"),
            ...$this->getArguments(),
            InputOption::make('force')
                ->shortcut('f')
                ->flag()
                ->description("Overwrite the {$this->type()} if it already exists"),
            ...$this->getOptions(),
        ];
    }

    /**
     * Execute the generator command.
     *
     * @return int Exit code (0 for success, 1 for error).
     */
    public function handle(): int
    {
        $rawName = $this->input->argument('name');

        if (empty($rawName)) {
            $this->output->error('  Missing required argument: name');
            return 1;
        }

        $name = $this->qualifyClass(trim($rawName));
        $path = $this->getPath($name);

        // Check if file already exists
        if (!$this->input->option('force') && $this->alreadyExists($path)) {
            $this->output->error("  {$this->type()} [{$name}] already exists.");
            $this->output->line('  Use --force to overwrite.');
            return 1;
        }

        // Ensure directory exists
        $this->makeDirectory(dirname($path));

        // Build file content
        $content = $this->buildClass($name);

        // Write file
        file_put_contents($path, $content);

        // Success output
        $this->output->success("  {$this->type()} [{$name}] created successfully.");
        $this->output->line('  → ' . $this->relativePath($path));

        $this->afterCreate($name, $path);

        return 0;
    }

    /**
     * Parse the class name and format according to the root namespace and sub-namespaces.
     *
     * @param string $name
     * @return string
     */
    protected function qualifyClass(string $name): string
    {
        $name = ltrim(str_replace('/', '\\', $name), '\\');
        $rootNamespace = $this->rootNamespace();

        if (str_starts_with($name, $rootNamespace . '\\')) {
            return $name;
        }

        return $this->getDefaultNamespace(trim($rootNamespace, '\\')) . '\\' . $name;
    }

    /**
     * Get the destination file path for the qualified class name.
     *
     * @param string $name Fully-qualified class name.
     * @return string Absolute file path.
     */
    protected function getPath(string $name): string
    {
        $rootNamespace = $this->rootNamespace() . '\\';
        $relativeClass = str_starts_with($name, $rootNamespace)
            ? substr($name, strlen($rootNamespace))
            : $name;

        $basePath = App::getInstance()->getBasePath();
        $path = $basePath . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

        return $path;
    }

    /**
     * Build the class with the given name from the stub.
     *
     * @param string $name Fully-qualified class name.
     * @return string File content.
     */
    protected function buildClass(string $name): string
    {
        $stub = file_get_contents($this->getStub());

        return $this->replaceNamespace($stub, $name)
            ->replaceClass($stub, $name)
            ->replacePlaceholders($stub, $name);
    }

    /**
     * Replace the namespace for the given stub.
     *
     * @param string $stub
     * @param string $name
     * @return static
     */
    protected function replaceNamespace(string &$stub, string $name): static
    {
        $namespace = $this->getNamespace($name);

        $stub = str_replace(
            ['{{namespace}}', '{{ namespace }}', '{{rootNamespace}}', '{{ rootNamespace }}'],
            [$namespace, $namespace, $this->rootNamespace(), $this->rootNamespace()],
            $stub
        );

        return $this;
    }

    /**
     * Replace the class name for the given stub.
     *
     * @param string $stub
     * @param string $name
     * @return static
     */
    protected function replaceClass(string &$stub, string $name): static
    {
        $class = $this->getClassName($name);

        $stub = str_replace(
            ['{{class}}', '{{ class }}', '{{name}}', '{{ name }}'],
            [$class, $class, $class, $class],
            $stub
        );

        return $this;
    }

    /**
     * Custom hook for subclasses to replace additional placeholders.
     *
     * @param string $stub
     * @param string $name
     * @return string
     */
    protected function replacePlaceholders(string $stub, string $name): string
    {
        return $stub;
    }

    /**
     * Get the namespace from a fully-qualified class name.
     *
     * @param string $name
     * @return string
     */
    protected function getNamespace(string $name): string
    {
        $parts = explode('\\', $name);
        array_pop($parts);

        return implode('\\', $parts);
    }

    /**
     * Get the short class name from a fully-qualified class name.
     *
     * @param string $name
     * @return string
     */
    protected function getClassName(string $name): string
    {
        $parts = explode('\\', $name);

        return end($parts);
    }

    /**
     * Check if a file already exists at the given path.
     *
     * @param string $path
     * @return bool
     */
    protected function alreadyExists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Create the directory if it does not already exist.
     *
     * @param string $path
     * @return void
     */
    protected function makeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Return a clean relative path from the application base directory.
     *
     * @param string $absolute
     * @return string
     */
    protected function relativePath(string $absolute): string
    {
        $base = App::getInstance()->getBasePath();

        return ltrim(str_replace($base, '', $absolute), '/');
    }

    /**
     * Hook called after the file has been successfully written.
     *
     * @param string $name Fully-qualified class name.
     * @param string $path Absolute file path.
     * @return void
     */
    protected function afterCreate(string $name, string $path): void
    {
        // Optional hook for subclasses
    }
}
