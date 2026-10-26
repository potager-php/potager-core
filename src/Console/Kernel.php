<?php

namespace Potager\Console;

use Potager\Console\Commands\CommandListCommand;
use Potager\Console\Commands\FreshCommand;
use Potager\Console\Commands\MakeCommandCommand;
use Potager\Console\Commands\MakeControllerCommand;
use Potager\Console\Commands\MakeMiddlewareCommand;
use Potager\Console\Commands\MakeMigrationCommand;
use Potager\Console\Commands\MakeModelCommand;
use Potager\Console\Commands\MakePipeCommand;
use Potager\Console\Commands\MigrateCommand;
use Potager\Console\Commands\RollbackCommand;
use Potager\Console\Commands\RouteListCommand;
use Potager\Console\Commands\WipeCommand;
use Potager\Console\Exceptions\CommandNotFoundException;
use Potager\Console\IO\InputDefinition;
use Potager\Console\IO\Output;
use Potager\Container\Container;
use Potager\Contracts\Console\InputInterface;

/**
 * Manages console command registration, resolution, and execution.
 *
 * Coordinates the full console lifecycle: bootstrapping built-in commands,
 * registering custom commands, resolving by name, delegating global options,
 * and dispatching execution through the middleware pipeline.
 */
class Kernel
{
    /** @var Container The application container used to resolve command dependencies. */
    private Container $container;

    /** @var array<string, Command> All registered commands, keyed by command name. */
    private array $commands = [];

    /** @var array<class-string> Middleware pipe classes to execute before the command handler. */
    private array $pipes = [];

    /** @var GlobalOptionsManager Manager for global options definition, protection, and execution. */
    private GlobalOptionsManager $globalOptions;

    /**
     * @param Container                 $container     The application container.
     * @param GlobalOptionsManager|null $globalOptions The global options manager instance.
     */
    public function __construct(Container $container, ?GlobalOptionsManager $globalOptions = null)
    {
        $this->container = $container;
        $this->globalOptions = $globalOptions ?? new GlobalOptionsManager();
    }

    /**
     * Bootstraps the kernel by registering all built-in commands.
     */
    public function bootstrap(): void
    {
        $this->registerBuiltins();
    }

    /**
     * Registers all built-in commands provided by the framework.
     */
    public function registerBuiltins(): void
    {
        $builtinCommands = [
            CommandListCommand::class,
            MakeCommandCommand::class,
            MakeControllerCommand::class,
            MakeMiddlewareCommand::class,
            MakeMigrationCommand::class,
            MakeModelCommand::class,
            MakePipeCommand::class,
            RouteListCommand::class,
            MigrateCommand::class,
            RollbackCommand::class,
            FreshCommand::class,
            WipeCommand::class,
        ];

        foreach ($builtinCommands as $commandClass) {
            $this->register($commandClass);
        }
    }

    /**
     * Registers a command class instance.
     *
     * The class must extend {@see Command}. The command is instantiated
     * and stored under its name.
     *
     * @param class-string<Command> $commandClass The fully-qualified command class name.
     */
    public function register(string $commandClass): void
    {
        /** @var Command $instance */
        $instance = $this->container->make($commandClass);
        $this->commands[$instance->getName()] = $instance;
    }

    /**
     * Registers an inline closure command.
     *
     * Creates a {@see ClosureCommand} instance wrapping the handler and returns
     * it for further fluent configuration (e.g., input definition, description).
     *
     * @param string   $name    The command name (e.g. "mail:send").
     * @param \Closure $handler The closure to invoke when the command is executed.
     * @return ClosureCommand The registered command instance for chaining.
     */
    public function command(string $name, \Closure $handler): ClosureCommand
    {
        $instance = new ClosureCommand($name, $handler);
        $this->commands[$instance->getName()] = $instance;
        return $instance;
    }

    /**
     * Resolves a command by name.
     *
     * @param string $name The command name.
     * @return Command The resolved command instance.
     *
     * @throws CommandNotFoundException If the command is not registered.
     */
    public function resolve(string $name): Command
    {
        if (isset($this->commands[$name])) {
            return $this->commands[$name];
        }

        foreach ($this->commands as $command) {
            if (in_array($name, $command->getAliases(), true)) {
                return $command;
            }
        }

        throw new CommandNotFoundException($name);
    }

    /**
     * Returns whether a command with the given name is registered.
     *
     * @param string $name The command name.
     * @return bool
     */
    public function has(string $name): bool
    {
        if (isset($this->commands[$name])) {
            return true;
        }

        foreach ($this->commands as $command) {
            if (in_array($name, $command->getAliases(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns all registered commands.
     *
     * @return array<string, Command> Commands keyed by name.
     */
    public function all(): array
    {
        return $this->commands;
    }

    /**
     * Assigns one or more global middleware pipes to the execution pipeline.
     *
     * Accepts a single class-string, multiple class-strings, or an array of class-strings.
     *
     * @param class-string<\Potager\Contracts\Console\PipeInterface>|array<class-string<\Potager\Contracts\Console\PipeInterface>> ...$pipes
     * @return self
     */
    public function through(string|array ...$pipes): self
    {
        foreach ($pipes as $item) {
            if (is_array($item)) {
                $this->pipes = array_merge($this->pipes, $item);
            } else {
                $this->pipes[] = $item;
            }
        }

        return $this;
    }

    /**
     * Appends a middleware pipe class to the pipeline.
     *
     * @param class-string $pipeClass
     * @return self
     */
    public function pushPipe(string $pipeClass): self
    {
        return $this->through($pipeClass);
    }

    /**
     * Sets all middleware pipes in the pipeline.
     *
     * @param array<class-string> $pipes
     * @return self
     */
    public function setPipes(array $pipes): self
    {
        $this->pipes = $pipes;
        return $this;
    }

    /**
     * Returns all registered middleware pipes.
     *
     * @return array<class-string>
     */
    public function getPipes(): array
    {
        return $this->pipes;
    }

    /**
     * Returns the global options manager.
     *
     * @return GlobalOptionsManager
     */
    public function getGlobalOptionsManager(): GlobalOptionsManager
    {
        return $this->globalOptions;
    }

    /**
     * Sets the global options manager.
     *
     * @param GlobalOptionsManager $globalOptions
     * @return self
     */
    public function setGlobalOptionsManager(GlobalOptionsManager $globalOptions): self
    {
        $this->globalOptions = $globalOptions;
        return $this;
    }

    /**
     * Convenience proxy returning the default input definition for global options.
     *
     * @return InputDefinition
     */
    public function getDefaultInputDefinition(): InputDefinition
    {
        return $this->globalOptions->getDefaultDefinition();
    }

    /**
     * Handles the full execution lifecycle for a console request.
     *
     * Resolves the command, merges global options, handles system parameters,
     * processes the middleware pipeline, and returns the resulting exit code.
     *
     * @param InputInterface $input  The parsed console input.
     * @param Output         $output The output handler.
     * @return int The exit code (0 for success, non-zero for failure).
     */
    public function handle(InputInterface $input, Output $output): int
    {
        try {
            return $this->executeCommand($input, $output);
        } catch (CommandNotFoundException $e) {
            $output->error('  ' . $e->getMessage());
            $output->newLine();
            $output->line('  Run ' . $output->format('php farmer list', 'yellow') . ' to see available commands.');
            $output->newLine();
            return 1;
        } catch (\LogicException $e) {
            $output->error('  ' . $e->getMessage());
            $output->newLine();
            return 1;
        } catch (\Exception $e) {
            $output->error('  Erreur fatale : ' . $e->getMessage());
            $output->newLine();
            return 1;
        }
    }

    /**
     * Executes a specific command with resolved definition, global options, and dependencies.
     *
     * @param InputInterface $input  The parsed console input.
     * @param Output         $output The output handler.
     * @return int The exit code returned by the command or early-exit handler.
     */
    protected function executeCommand(InputInterface $input, Output $output): int
    {
        // 1. Définition de la commande ciblée ('list' par défaut)
        $commandName = $input->getCommandName() ?? 'list';

        // 2. Résolution de la commande
        $command = $this->resolve($commandName);

        // 3. Construction de la définition combinée avec protection anti-surcharge via GlobalOptionsManager
        $definition = $this->globalOptions->buildDefinitionFor($command);

        // 4. Liaison de l'input et injection IO
        $input->bindDefinition($definition);
        $command->setIO($input, $output);
        $command->setDefinition($definition);

        // 5. Bloc dédié délégué à GlobalOptionsManager (early-exit sur --help ou --version, ou configuration)
        $earlyExit = $this->globalOptions->handle($input, $output, $command);
        if ($earlyExit !== null) {
            return $earlyExit;
        }

        // 6. Validation de l'input pour l'exécution réelle
        $input->validate();

        // 7. Exécution via le pipeline de middlewares éventuel ou directement
        return $this->runPipeline($command, $input, $output);
    }

    /**
     * Executes the command within the registered middleware pipeline.
     *
     * @param Command        $command The resolved command.
     * @param InputInterface $input   The bound input.
     * @param Output         $output  The output handler.
     * @return int
     */
    protected function runPipeline(Command $command, InputInterface $input, Output $output): int
    {
        $pipes = array_merge($this->pipes, $command->getPipes());
        $core = fn(): int => $this->callCommandHandler($command, $input, $output);

        if (empty($pipes)) {
            return $core();
        }

        $pipeline = array_reverse($pipes);
        $next = $core;

        foreach ($pipeline as $pipeClass) {
            $prev = $next;
            $next = function () use ($pipeClass, $input, $output, $prev): int {
                $pipe = $this->container->make($pipeClass);
                return $pipe->handle($input, $output, $prev);
            };
        }

        return $next();
    }

    /**
     * Invokes the command handler through the container.
     *
     * @param Command        $command The resolved command.
     * @param InputInterface $input   The bound input.
     * @param Output         $output  The output handler.
     * @return int
     */
    protected function callCommandHandler(Command $command, InputInterface $input, Output $output): int
    {
        $result = $this->container->call([$command, 'handle'], [
            'input' => $input,
            'output' => $output,
        ]);

        return is_int($result) ? $result : 0;
    }
}
