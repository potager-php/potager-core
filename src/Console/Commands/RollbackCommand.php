<?php

namespace Potager\Console\Commands;

use Potager\App;
use Potager\Console\Command;
use Potager\Console\IO\InputOption;
use Potager\Database\Migrator;

/**
 * Command to rollback migration batches or steps.
 */
class RollbackCommand extends Command
{
    protected string $name = 'db:rollback';
    protected array $aliases = ['migrate:rollback'];
    protected string $description = 'Rollback the last database migration batch';

    protected function getInputDefinition(): array
    {
        return [
            InputOption::make('path')
                ->acceptValue()
                ->description('The path to the migrations files to be executed'),
            InputOption::make('step')
                ->acceptValue()
                ->description('The number of migrations to be reverted'),
            InputOption::make('batch')
                ->acceptValue()
                ->description('The batch of migrations to be reverted'),
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
            if ($event === 'reverting') {
                $this->output->line($this->output->format('  ROLLING BACK  ', 'yellow') . $name);
            } elseif ($event === 'reverted') {
                $this->output->line(
                    $this->output->format('  ROLLED BACK   ', 'green') . $name .
                    $this->output->format(" ({$duration}ms)", 'dim')
                );
            }
        });

        $step = $this->input->option('step') !== null ? (int) $this->input->option('step') : null;
        $batch = $this->input->option('batch') !== null ? (int) $this->input->option('batch') : null;

        $rolledBack = $migrator->rollback($path, [
            'step'  => $step,
            'batch' => $batch,
        ]);

        if (empty($rolledBack)) {
            $this->output->info('  Nothing to rollback.');
            return 0;
        }

        $this->output->newLine();
        $this->output->success('  Database rollback completed.');

        return 0;
    }
}
