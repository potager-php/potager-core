<?php

namespace Potager\Facades;

use Potager\Console\ClosureCommand;
use Potager\Console\Command;
use Potager\Console\GlobalOptionsManager;
use Potager\Console\IO\Output;
use Potager\Console\Kernel;
use Potager\Contracts\Console\InputInterface;
use Potager\Support\Facades\Facade;

/**
 * Facade for the Console Kernel.
 *
 * Provides static access to command registration, resolution, global options, and pipeline management.
 *
 * @method static void register(string $commandClass)
 * @method static ClosureCommand command(string $name, \Closure $handler)
 * @method static Command resolve(string $name)
 * @method static bool has(string $name)
 * @method static array<string, Command> all()
 * @method static Kernel through(string|array ...$pipes)
 * @method static Kernel pushPipe(string $pipeClass)
 * @method static Kernel setPipes(array $pipes)
 * @method static array<string> getPipes()
 * @method static GlobalOptionsManager getGlobalOptionsManager()
 * @method static Kernel setGlobalOptionsManager(GlobalOptionsManager $globalOptions)
 * @method static int handle(InputInterface $input, Output $output)
 *
 * @see \Potager\Console\Kernel
 */
class Farmer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Kernel::class;
    }
}
