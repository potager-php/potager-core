<?php

namespace Potager\Console\Commands;

use Potager\App;
use Potager\Console\Command;
use Potager\Console\IO\InputArgument;
use Potager\Console\IO\InputOption;

/**
 * Command to generate a new database migration file.
 */
class MakeMigrationCommand extends Command
{
    protected string $name = 'make:migration';
    protected array $aliases = ['make'];
    protected string $description = 'Create a new migration file';

    protected function getInputDefinition(): array
    {
        return [
            InputArgument::make('name')
                ->description('The name of the migration to create'),
            InputOption::make('create')
                ->acceptValue()
                ->description('The table to be created'),
            InputOption::make('table')
                ->acceptValue()
                ->description('The table to migrate'),
            InputOption::make('path')
                ->acceptValue()
                ->description('The directory where the migration file should be placed'),
        ];
    }

    public function handle(): int
    {
        $rawName = $this->input->argument('name');

        if (empty($rawName)) {
            $this->output->error('  Missing required argument: name');
            return 1;
        }

        $snakeName = $this->normalizeName(trim($rawName));

        [$stubPath, $tableName] = $this->resolveStubAndTable($snakeName);

        $stub = file_get_contents($stubPath);
        $content = str_replace(
            ['{{table}}', '{{ table }}'],
            [$tableName, $tableName],
            $stub
        );

        $basePath = App::getInstance()->getBasePath();
        $targetDir = $this->input->option('path') ?? ($basePath . '/database/migrations');

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filename = date('Y_m_d_His') . '_' . $snakeName . '.php';
        $fullPath = rtrim($targetDir, '/') . '/' . $filename;

        file_put_contents($fullPath, $content);

        $this->output->success("  Migration [{$filename}] created successfully.");
        $this->output->line('  → ' . $this->relativePath($fullPath));

        return 0;
    }

    /**
     * Converts class name or arbitrary string to snake_case.
     */
    protected function normalizeName(string $name): string
    {
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
        $snake = preg_replace('/[^a-z0-9_]/', '_', $snake);

        return trim(preg_replace('/_+/', '_', $snake), '_');
    }

    /**
     * Determine which stub to use and the inferred table name.
     *
     * @param string $snakeName
     * @return array{0: string, 1: string} [stubPath, tableName]
     */
    protected function resolveStubAndTable(string $snakeName): array
    {
        $stubsDir = __DIR__ . '/../Stubs';

        $createOption = $this->input->option('create');
        if (!empty($createOption)) {
            return [$stubsDir . '/migration.create.stub', (string) $createOption];
        }

        $tableOption = $this->input->option('table');
        if (!empty($tableOption)) {
            return [$stubsDir . '/migration.table.stub', (string) $tableOption];
        }

        if (preg_match('/^create_(.+)_table$/', $snakeName, $matches)) {
            return [$stubsDir . '/migration.create.stub', $matches[1]];
        }

        if (preg_match('/_(?:to|from|in)_(.+)_table$/', $snakeName, $matches)) {
            return [$stubsDir . '/migration.table.stub', $matches[1]];
        }

        return [$stubsDir . '/migration.blank.stub', ''];
    }

    /**
     * Convert absolute path to path relative to project root.
     */
    protected function relativePath(string $path): string
    {
        $basePath = App::getInstance()->getBasePath();
        if (str_starts_with($path, $basePath)) {
            return ltrim(substr($path, strlen($basePath)), '/\\');
        }

        return $path;
    }
}
