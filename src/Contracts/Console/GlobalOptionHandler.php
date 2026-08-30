<?php

namespace Potager\Contracts\Console;

use Potager\Console\Command;
use Potager\Console\IO\InputOption;
use Potager\Console\IO\Output;

/**
 * Defines a global console option and its execution handling behavior.
 */
interface GlobalOptionHandler
{
    /**
     * Returns the input option definition for this global option.
     *
     * @return InputOption
     */
    public function getOption(): InputOption;

    /**
     * Handles the global option when present on the input.
     *
     * Returns an integer exit code for early termination (e.g. 0 for help or version),
     * or null to allow command execution to proceed.
     *
     * @param InputInterface $input   The bound input instance.
     * @param Output         $output  The output handler.
     * @param Command        $command The target command.
     * @return int|null Exit code for early termination, or null to continue.
     */
    public function handle(InputInterface $input, Output $output, Command $command): ?int;
}
