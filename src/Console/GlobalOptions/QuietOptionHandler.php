<?php

namespace Potager\Console\GlobalOptions;

use Potager\Console\Command;
use Potager\Console\IO\InputOption;
use Potager\Console\IO\Output;
use Potager\Contracts\Console\GlobalOptionHandler;
use Potager\Contracts\Console\InputInterface;

/**
 * Handles the global --quiet / -q option.
 */
class QuietOptionHandler implements GlobalOptionHandler
{
    public function getOption(): InputOption
    {
        return InputOption::make('quiet')
            ->shortcut('q')
            ->flag()
            ->description('Do not output any message.');
    }

    public function handle(InputInterface $input, Output $output, Command $command): ?int
    {
        if ($input->hasOption('quiet') && $input->option('quiet')) {
            $output->setQuiet(true);
        }

        return null;
    }
}
