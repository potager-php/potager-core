<?php

namespace Potager\Console\GlobalOptions;

use Potager\Console\Command;
use Potager\Console\IO\InputOption;
use Potager\Console\IO\Output;
use Potager\Contracts\Console\GlobalOptionHandler;
use Potager\Contracts\Console\InputInterface;

/**
 * Handles the global --env option.
 */
class EnvOptionHandler implements GlobalOptionHandler
{
    public function getOption(): InputOption
    {
        return InputOption::make('env')
            ->acceptValue()
            ->description('The environment the command should run under.');
    }

    public function handle(InputInterface $input, Output $output, Command $command): ?int
    {
        return null;
    }
}
