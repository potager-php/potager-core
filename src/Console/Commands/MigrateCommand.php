<?php

namespace Potager\Console\Commands;

use Potager\App;
use Potager\Console\Command;
use Potager\Console\IO\InputOption;
use Potager\Database\Migrator;

/**
 * Command to execute pending database migrations.
 */
class MigrateCommand extends Command
{
    protected string $name = 'db:migrate';
    protected array $aliases = ['migrate'];
    protected string $description = 'Run pending database migrations';

    protected function getInputDefinition(): array
    {
        return [
            InputOption::make('path')
                ->acceptValue()
                ->description('The path to the migrations files to be executed'),
            InputOption::make('force')
                ->flag()
                ->description('Force the operation to run when in production'),
        ];
    }

    public function handle(?Migrator $migrator = null): int
    {
        $migrator ??= App::make(Migrator::class);

        $basePath = App::getInstance()->getBasePath();
        $path = $this->input->option('path') ?? ($basePath . '/database/migrations');

        $migrator->setOutputCallback(function (string $event, string $name, float $duration = 0.0) {
            if ($event === 'running') {
                $this->output->line($this->output->format('  RUNNING  ', 'yellow') . $name);
            } elseif ($event === 'migrated') {
                $this->output->line(
                    $this->output->format('  DONE     ', 'green') . $name .
                    $this->output->format(" ({$duration}ms)", 'dim')
                );
            }
        });

        $ran = $migrator->run($path);

        if (empty($ran)) {
            $this->output->info('  Nothing to migrate.');
            return 0;
        }

        $this->output->newLine();
        $this->output->success('  Database migrated successfully.');

        return 0;
    }
}
