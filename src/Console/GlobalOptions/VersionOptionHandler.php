<?php

namespace Potager\Console\GlobalOptions;

use Potager\App;
use Potager\Console\Command;
use Potager\Console\IO\InputOption;
use Potager\Console\IO\Output;
use Potager\Contracts\Console\GlobalOptionHandler;
use Potager\Contracts\Console\InputInterface;

/**
 * Handles the global --version / -v option.
 */
class VersionOptionHandler implements GlobalOptionHandler
{
    /** @var string|null Custom version override, or null to resolve via App::version(). */
    private ?string $customVersion;

    /**
     * @param string|null $customVersion Optional custom version string.
     */
    public function __construct(?string $customVersion = null)
    {
        $this->customVersion = $customVersion;
    }

    public function getOption(): InputOption
    {
        return InputOption::make('version')
            ->shortcut('v')
            ->flag()
            ->description('Display this application version.');
    }

    public function handle(InputInterface $input, Output $output, Command $command): ?int
    {
        if ($input->hasOption('version') && $input->option('version')) {
            $name = App::name('Potager Framework');
            $version = $this->getVersion();

            $output->line("{$name} version {$version}");
            return 0;
        }

        return null;
    }

    /**
     * Returns the resolved application version.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->customVersion ?? App::version();
    }

    /**
     * Sets a custom version override.
     *
     * @param string|null $version
     * @return self
     */
    public function setVersion(?string $version): self
    {
        $this->customVersion = $version;
        return $this;
    }
}
