<?php

namespace Potager\Console\GlobalOptions;

use Potager\Console\Command;
use Potager\Console\IO\InputOption;
use Potager\Console\IO\Output;
use Potager\Contracts\Console\GlobalOptionHandler;
use Potager\Contracts\Console\InputInterface;

/**
 * Handles the global --no-interaction / -n option.
 */
class NoInteractionOptionHandler implements GlobalOptionHandler
{
    public function getOption(): InputOption
    {
        return InputOption::make('no-interaction')
            ->shortcut('n')
            ->flag()
            ->description('Do not ask any interactive question.');
    }

    public function handle(InputInterface $input, Output $output, Command $command): ?int
    {
        if ($input->hasOption('no-interaction') && $input->option('no-interaction')) {
            $input->setInteractive(false);
        }

        return null;
    }
}
