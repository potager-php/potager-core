<?php

namespace Potager\Console;

use Potager\Console\GlobalOptions\EnvOptionHandler;
use Potager\Console\GlobalOptions\HelpOptionHandler;
use Potager\Console\GlobalOptions\NoInteractionOptionHandler;
use Potager\Console\GlobalOptions\QuietOptionHandler;
use Potager\Console\GlobalOptions\VersionOptionHandler;
use Potager\Console\IO\InputDefinition;
use Potager\Console\IO\Output;
use Potager\Contracts\Console\GlobalOptionHandler;
use Potager\Contracts\Console\InputInterface;

/**
 * Manages global console options: their definition, protection against overrides,
 * and dispatching execution to dedicated GlobalOptionHandler strategies.
 */
class GlobalOptionsManager
{
    /** @var array<GlobalOptionHandler> List of registered global option handlers. */
    private array $handlers = [];

    /**
     * @param array<GlobalOptionHandler> $handlers Optional list of custom handlers.
     */
    public function __construct(array $handlers = [])
    {
        $this->handlers = $handlers ?: $this->defaultHandlers();
    }

    /**
     * Returns default global option handlers.
     *
     * @return array<GlobalOptionHandler>
     */
    protected function defaultHandlers(): array
    {
        return [
            new HelpOptionHandler(),
            new VersionOptionHandler(),
            new NoInteractionOptionHandler(),
            new QuietOptionHandler(),
            new EnvOptionHandler(),
        ];
    }

    /**
     * Registers a new global option handler.
     *
     * @param GlobalOptionHandler $handler
     * @return self
     */
    public function register(GlobalOptionHandler $handler): self
    {
        $this->handlers[] = $handler;
        return $this;
    }

    /**
     * Returns all registered handlers.
     *
     * @return array<GlobalOptionHandler>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Returns the base input definition containing all registered global options.
     *
     * @return InputDefinition
     */
    public function getDefaultDefinition(): InputDefinition
    {
        $definition = new InputDefinition();

        foreach ($this->handlers as $handler) {
            $definition->addOption($handler->getOption());
        }

        return $definition;
    }

    /**
     * Builds a combined input definition for a command, ensuring no command
     * overrides any reserved global option or shortcut.
     *
     * @param Command $command The target command.
     * @return InputDefinition The merged, protected definition.
     *
     * @throws \LogicException If the command tries to override a reserved global option or shortcut.
     */
    public function buildDefinitionFor(Command $command): InputDefinition
    {
        $commandDef = $command->getResolvedInputDefinition();
        $appDef = $this->getDefaultDefinition();

        // Strict protection against overrides
        foreach ($commandDef->getOptions() as $option) {
            $name = $option->getName();
            $shortcut = $option->getShortcut();

            if ($appDef->hasOption($name)) {
                throw new \LogicException(sprintf(
                    'Command "%s" cannot override reserved application option "--%s".',
                    $command->getName(),
                    $name
                ));
            }

            if ($shortcut !== null && $appDef->hasShortcut($shortcut)) {
                throw new \LogicException(sprintf(
                    'Command "%s" cannot override reserved application shortcut "-%s" (reserved for "--%s").',
                    $command->getName(),
                    $shortcut,
                    $appDef->shortcutToName($shortcut)
                ));
            }
        }

        $combined = new InputDefinition();
        $combined->setCommandName($command->getName());
        $combined->merge($commandDef);
        $combined->merge($appDef);

        return $combined;
    }

    /**
     * Handles global options on the bound input by delegating to registered handlers.
     *
     * Returns an integer exit code if early termination is requested (e.g. --version or --help),
     * or null if execution should proceed to the command handler.
     *
     * @param InputInterface $input   The bound input instance.
     * @param Output         $output  The output handler.
     * @param Command        $command The resolved command.
     * @return int|null The exit code for early return, or null to continue.
     */
    public function handle(InputInterface $input, Output $output, Command $command): ?int
    {
        foreach ($this->handlers as $handler) {
            $exitCode = $handler->handle($input, $output, $command);
            if ($exitCode !== null) {
                return $exitCode;
            }
        }

        return null;
    }
}
