<?php

namespace Potager\Console;

use Potager\Contracts\Console\InputInterface;
use Potager\Console\IO\InputDefinition;
use Potager\Console\IO\Output;

/**
 * Base class for all console commands.
 *
 * Subclasses should override {@see getInputDefinition()} to declare expected
 * arguments and options, and implement a `handle()` method to define the
 * command's behaviour.
 */
abstract class Command
{
    /** @var string The name of the command (e.g. "mail:send"). */
    protected string $name = '';

    /** @var string A short description of the command shown in the command list. */
    protected string $description = '';

    /** @var string Detailed help text or examples shown in the command help page. */
    protected string $helpText = '';

    /** @var array<string> Alternative names (aliases) for this command. */
    protected array $aliases = [];

    /** @var InputInterface The resolved input instance bound to this command. */
    protected InputInterface $input;

    /** @var Output The output instance bound to this command. */
    protected Output $output;

    /** @var InputDefinition|null The full input definition (including application options) bound to this command. */
    protected ?InputDefinition $definition = null;

    /** @var array<class-string<\Potager\Contracts\Console\PipeInterface>> Middleware pipes specific to this command. */
    protected array $pipes = [];

    /** @var array<\Potager\Console\IO\InputArgument> Arguments declared for this command. */
    protected array $arguments = [];

    /** @var array<\Potager\Console\IO\InputOption> Options declared for this command. */
    protected array $options = [];

    /**
     * Returns additional arguments declared for this command.
     *
     * @return array<\Potager\Console\IO\InputArgument>
     */
    protected function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Returns additional options declared for this command.
     *
     * @return array<\Potager\Console\IO\InputOption>
     */
    protected function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Returns the raw input definition for this command.
     *
     * Override in subclasses to declare {@see \Potager\Console\IO\InputArgument}
     * and {@see \Potager\Console\IO\InputOption} instances, or implement
     * {@see getArguments()} and {@see getOptions()}.
     *
     * @return array<\Potager\Console\IO\InputArgument|\Potager\Console\IO\InputOption>
     */
    protected function getInputDefinition(): array
    {
        return [
            ...$this->getArguments(),
            ...$this->getOptions(),
        ];
    }

    /**
     * Builds and returns the resolved {@see InputDefinition} for this command.
     *
     * @return InputDefinition
     */
    public function getResolvedInputDefinition(): InputDefinition
    {
        return new InputDefinition($this->getInputDefinition());
    }

    /**
     * Sets the bound input definition for this command.
     *
     * @param InputDefinition $definition
     */
    public function setDefinition(InputDefinition $definition): void
    {
        $this->definition = $definition;
    }

    /**
     * Returns the bound input definition for this command, or null if not yet bound.
     *
     * @return InputDefinition|null
     */
    public function getDefinition(): ?InputDefinition
    {
        return $this->definition;
    }

    /**
     * Sets the input instance for this command.
     *
     * @param InputInterface $input The parsed input.
     */
    public function setInput(InputInterface $input): void
    {
        $this->input = $input;
    }

    /**
     * Sets the output instance for this command.
     *
     * @param Output $output The output handler.
     */
    public function setOutput(Output $output): void
    {
        $this->output = $output;
    }

    /**
     * Sets both the input and output instances in one call.
     *
     * @param InputInterface $input  The parsed input.
     * @param Output         $output The output handler.
     */
    public function setIO(InputInterface $input, Output $output): void
    {
        $this->setInput($input);
        $this->setOutput($output);
    }

    /**
     * Returns the name of the command.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the description of the command.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Sets the short description of the command.
     *
     * @param string $description
     * @return static
     */
    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Returns detailed help text or examples for the command.
     *
     * @return string
     */
    public function getHelpText(): string
    {
        return $this->helpText;
    }

    /**
     * Sets detailed help text or examples for the command.
     *
     * @param string $helpText
     * @return static
     */
    public function setHelpText(string $helpText): static
    {
        $this->helpText = $helpText;
        return $this;
    }

    /**
     * Fluent alias to set detailed help text.
     *
     * @param string $helpText
     * @return static
     */
    public function helpText(string $helpText): static
    {
        return $this->setHelpText($helpText);
    }

    /**
     * Returns all command aliases.
     *
     * @return array<string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Sets command aliases.
     *
     * @param array<string> $aliases
     * @return static
     */
    public function setAliases(array $aliases): static
    {
        $this->aliases = $aliases;
        return $this;
    }

    /**
     * Fluent alias to assign command aliases.
     *
     * @param array<string> $aliases
     * @return static
     */
    public function aliases(array $aliases): static
    {
        return $this->setAliases($aliases);
    }

    /**
     * Renders a comprehensive, beautifully aligned help page for this command.
     *
     * @param InputDefinition|null $definition Optional definition to use for rendering help.
     */
    public function help(?InputDefinition $definition = null): void
    {
        $definition ??= $this->definition ?? $this->getResolvedInputDefinition();
        $arguments = $definition->getArguments();
        $options = $definition->getOptions();

        $this->output->newLine();

        // 1. Description
        $description = $this->getDescription();
        if (!empty($description)) {
            $this->output->line($this->output->format('Description:', 'yellow'));
            $this->output->line('  ' . $description);
            $this->output->newLine();
        }

        // 2. Usage (Standard : commande [options] [--] <arguments>)
        $this->output->line($this->output->format('Usage:', 'yellow'));

        $usages = [$this->getName(), ...$this->getAliases()];
        foreach ($usages as $commandName) {
            $synopsis = '  php farmer ' . $commandName;

            // 1. Options
            if (!empty($options)) {
                $synopsis .= ' [options]';
            }

            // 2. Arguments
            if (!empty($arguments)) {
                $synopsis .= ' [--]';
                foreach ($arguments as $arg) {
                    $name = $arg->getName();

                    if ($arg->isMultiple()) {
                        if ($arg->isRequired()) {
                            $synopsis .= ' <' . $name . '>...';
                        } else {
                            $synopsis .= ' [<' . $name . '>...]';
                        }
                    } else {
                        if ($arg->isRequired()) {
                            $synopsis .= ' <' . $name . '>';
                        } else {
                            $synopsis .= ' [<' . $name . '>]';
                        }
                    }
                }
            }

            $this->output->line($synopsis);
        }
        $this->output->newLine();

        // Pre-compute arguments formatting
        $maxArgLen = 0;
        $argRows = [];
        foreach ($arguments as $arg) {
            $name = $arg->getName();
            if ($arg->isMultiple()) {
                $name .= '...';
            }

            $maxArgLen = max($maxArgLen, strlen($name));
            $argRows[] = [
                'name' => $name,
                'description' => $arg->getDescription(),
                'default' => $arg->getDefault(),
            ];
        }

        // Pre-compute options formatting
        $maxOptLen = 0;
        $optRows = [];
        foreach ($options as $opt) {
            $shortcut = $opt->getShortcut() ? '-' . $opt->getShortcut() . ', ' : '    ';
            $name = '--' . $opt->getName();

            if ($opt->acceptsValue()) {
                $valLabel = strtoupper($opt->getName());
                if ($opt->isMultiple()) {
                    $name .= '=' . $valLabel . '...';
                } elseif ($opt->isValueRequired()) {
                    $name .= '=' . $valLabel;
                } else {
                    $name .= '[=' . $valLabel . ']';
                }
            }

            $synopsis = $shortcut . $name;
            $maxOptLen = max($maxOptLen, strlen($synopsis));

            $optRows[] = [
                'synopsis' => $synopsis,
                'description' => $opt->getDescription(),
                'default' => $opt->getDefault(),
                'isFlag' => $opt->isFlag(),
            ];
        }

        // Unified column padding for both Arguments and Options
        $padding = max($maxArgLen, $maxOptLen, 22) + 4;

        // 3. Arguments Section
        if (!empty($arguments)) {
            $this->output->line($this->output->format('Arguments:', 'yellow'));

            foreach ($argRows as $row) {
                $default = ($row['default'] !== null && $row['default'] !== false && $row['default'] !== [])
                    ? ' [default: ' . json_encode($row['default']) . ']'
                    : '';

                $this->output->line(
                    '  ' . $this->output->format(str_pad($row['name'], $padding), 'green') .
                    $row['description'] .
                    $this->output->format($default, 'yellow')
                );
            }
            $this->output->newLine();
        }

        // 4. Options Section
        if (!empty($options)) {
            $this->output->line($this->output->format('Options:', 'yellow'));

            foreach ($optRows as $row) {
                $default = (!$row['isFlag'] && $row['default'] !== null && $row['default'] !== false && $row['default'] !== [])
                    ? ' [default: ' . json_encode($row['default']) . ']'
                    : '';

                $this->output->line(
                    '  ' . $this->output->format(str_pad($row['synopsis'], $padding), 'green') .
                    $row['description'] .
                    $this->output->format($default, 'yellow')
                );
            }
            $this->output->newLine();
        }

        // 5. Extended Help Text (Optional)
        $helpText = $this->getHelpText();
        if (!empty($helpText)) {
            $this->output->line($this->output->format('Help:', 'yellow'));
            $lines = explode("\n", trim($helpText));
            foreach ($lines as $line) {
                $this->output->line('  ' . $line);
            }
            $this->output->newLine();
        }
    }

    /**
     * Returns all middleware pipes registered for this command.
     *
     * @return array<class-string<\Potager\Contracts\Console\PipeInterface>>
     */
    public function getPipes(): array
    {
        return $this->pipes;
    }

    /**
     * Attaches one or more middleware pipes to this command's execution pipeline.
     *
     * Accepts a single class-string, multiple class-strings, or an array of class-strings.
     *
     * @param class-string<\Potager\Contracts\Console\PipeInterface>|array<class-string<\Potager\Contracts\Console\PipeInterface>> ...$pipes
     * @return static
     */
    public function through(string|array ...$pipes): static
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
     * Appends a middleware pipe class to this command's execution pipeline.
     *
     * @param class-string<\Potager\Contracts\Console\PipeInterface> $pipeClass
     * @return static
     */
    public function pushPipe(string $pipeClass): static
    {
        return $this->through($pipeClass);
    }

    /**
     * Fluent alias for pushPipe.
     *
     * @param class-string<\Potager\Contracts\Console\PipeInterface> $pipeClass
     * @return static
     */
    public function pipe(string $pipeClass): static
    {
        return $this->through($pipeClass);
    }

    /**
     * Sets all middleware pipes for this command.
     *
     * @param array<class-string<\Potager\Contracts\Console\PipeInterface>> $pipes
     * @return static
     */
    public function setPipes(array $pipes): static
    {
        $this->pipes = $pipes;
        return $this;
    }

    /**
     * Fluent alias to assign multiple middleware pipes.
     *
     * @param array<class-string<\Potager\Contracts\Console\PipeInterface>> $pipes
     * @return static
     */
    public function withPipes(array $pipes): static
    {
        return $this->through($pipes);
    }
}
