<?php

namespace Potager\Console\GlobalOptions;

use Potager\Console\Command;
use Potager\Console\IO\InputOption;
use Potager\Console\IO\Output;
use Potager\Contracts\Console\GlobalOptionHandler;
use Potager\Contracts\Console\InputInterface;

/**
 * Handles the global --help / -h option.
 */
class HelpOptionHandler implements GlobalOptionHandler
{
    public function getOption(): InputOption
    {
        return InputOption::make('help')
            ->shortcut('h')
            ->flag()
            ->description('Display help for the given command. When no command is given, display help for the list command.');
    }

    public function handle(InputInterface $input, Output $output, Command $command): ?int
    {
        if ($input->hasOption('help') && $input->option('help')) {
            $command->help();
            return 0;
        }

        return null;
    }
}
