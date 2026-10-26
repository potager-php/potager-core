<?php

namespace Potager\Console\Commands;

use Potager\App;
use Potager\Console\Command;
use Potager\Console\IO\InputOption;
use Potager\Database\Migrator;

/**
 * Command to drop all tables from the database.
 */
class WipeCommand extends Command
{
    protected string $name = 'db:wipe';
    protected array $aliases = ['db:clean'];
    protected string $description = 'Drop all tables from the database';

    protected function getInputDefinition(): array
    {
        return [
            InputOption::make('force')
                ->flag()
                ->description('Force the operation to run when in production'),
        ];
    }

    public function handle(?Migrator $migrator = null): int
    {
        $migrator ??= App::make(Migrator::class);

        $this->output->line($this->output->format('  Dropping all tables...', 'yellow'));
        $migrator->wipe();
        $this->output->success('  Dropped all tables successfully.');

        return 0;
    }
}
