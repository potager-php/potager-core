<?php

use Potager\Console\Command;
use Potager\Console\IO\ArgvInput;
use Potager\Console\IO\InputArgument;
use Potager\Console\IO\InputOption;
use Potager\Console\IO\Output;
use Potager\Console\Kernel;
use Potager\Container\Container;
use Potager\Contracts\Console\InputInterface;
use Potager\Contracts\Console\PipeInterface;

class DummySimpleCommand extends Command
{
    protected string $name = 'test:simple';
    protected string $description = 'A simple test command';

    public function handle(Output $output): int
    {
        $output->line('Simple command executed');
        return 0;
    }
}

class DummyRequiredArgCommand extends Command
{
    protected string $name = 'test:required';
    protected string $description = 'A command with required argument';

    protected function getInputDefinition(): array
    {
        return [
            InputArgument::make('username')->required()->description('The username'),
        ];
    }

    public function handle(Output $output): int
    {
        $output->line('User: ' . $this->input->argument('username'));
        return 0;
    }
}

class DummyConflictOptionCommand extends Command
{
    protected string $name = 'test:conflict-opt';

    protected function getInputDefinition(): array
    {
        return [
            InputOption::make('help')->description('Overriding help option'),
        ];
    }

    public function handle(): int
    {
        return 0;
    }
}

class DummyConflictShortcutCommand extends Command
{
    protected string $name = 'test:conflict-shortcut';

    protected function getInputDefinition(): array
    {
        return [
            InputOption::make('custom')->shortcut('h')->description('Overriding h shortcut'),
        ];
    }

    public function handle(): int
    {
        return 0;
    }
}

class DummyInteractiveCommand extends Command
{
    protected string $name = 'test:interactive';

    public function handle(Output $output): int
    {
        $status = $this->input->isInteractive() ? 'interactive' : 'non-interactive';
        $output->line("Mode: {$status}");
        return 0;
    }
}

class DummySamplePipe implements PipeInterface
{
    public function handle(InputInterface $input, Output $output, callable $next): int
    {
        $output->line('Global Pipe Before');
        $code = $next();
        $output->line('Global Pipe After');
        return $code;
    }
}

class DummyCommandSpecificPipe implements PipeInterface
{
    public function handle(InputInterface $input, Output $output, callable $next): int
    {
        $output->line('Command Pipe Before');
        $code = $next();
        $output->line('Command Pipe After');
        return $code;
    }
}

class DummyCommandWithPipes extends Command
{
    protected string $name = 'test:with-pipes';
    protected array $pipes = [DummyCommandSpecificPipe::class];

    public function handle(Output $output): int
    {
        $output->line('Command With Pipes Executed');
        return 0;
    }
}

describe('Console Kernel & Application Parameters', function () {
    beforeEach(function () {
        $this->container = new Container();
        $this->kernel = new Kernel($this->container);
        $this->kernel->register(DummySimpleCommand::class);
        $this->kernel->register(DummyRequiredArgCommand::class);
        $this->kernel->register(DummyInteractiveCommand::class);
    });

    it('defines default application options', function () {
        $def = $this->kernel->getDefaultInputDefinition();

        expect($def->hasOption('help'))->toBeTrue();
        expect($def->hasShortcut('h'))->toBeTrue();
        expect($def->hasOption('version'))->toBeTrue();
        expect($def->hasShortcut('v'))->toBeTrue();
        expect($def->hasOption('no-interaction'))->toBeTrue();
        expect($def->hasShortcut('n'))->toBeTrue();
        expect($def->hasOption('quiet'))->toBeTrue();
        expect($def->hasShortcut('q'))->toBeTrue();
        expect($def->hasOption('env'))->toBeTrue();
    });

    it('protects application options from being overridden by command options', function () {
        $cmd = new DummyConflictOptionCommand();
        expect(fn() => $this->kernel->getGlobalOptionsManager()->buildDefinitionFor($cmd))->toThrow(
            LogicException::class,
            'Command "test:conflict-opt" cannot override reserved application option "--help".'
        );

        $this->kernel->register(DummyConflictOptionCommand::class);
        $input = new ArgvInput(['farmer', 'test:conflict-opt']);
        $output = new Output(false);

        $code = $this->kernel->handle($input, $output);
        expect($code)->toBe(1);
    });

    it('protects application shortcuts from being overridden by command shortcuts', function () {
        $cmd = new DummyConflictShortcutCommand();
        expect(fn() => $this->kernel->getGlobalOptionsManager()->buildDefinitionFor($cmd))->toThrow(
            LogicException::class,
            'Command "test:conflict-shortcut" cannot override reserved application shortcut "-h" (reserved for "--help").'
        );

        $this->kernel->register(DummyConflictShortcutCommand::class);
        $input = new ArgvInput(['farmer', 'test:conflict-shortcut']);
        $output = new Output(false);

        $code = $this->kernel->handle($input, $output);
        expect($code)->toBe(1);
    });

    it('handles --version option and exits early with 0', function () {
        $input = new ArgvInput(['farmer', 'test:simple', '--version']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $content = ob_get_clean();

        expect($code)->toBe(0);
        expect($content)->toContain('Potager Framework version');
        expect($content)->not->toContain('Simple command executed');
    });

    it('handles --help option without failing on missing required arguments', function () {
        $input = new ArgvInput(['farmer', 'test:required', '--help']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $content = ob_get_clean();

        expect($code)->toBe(0);
        expect($content)->toContain('A command with required argument');
        expect($content)->toContain('--help');
        expect($content)->toContain('--version');
        expect($content)->toContain('--no-interaction');
        expect($content)->toContain('--quiet');
        expect($content)->toContain('username');
    });

    it('handles --no-interaction by disabling interactive mode', function () {
        $input = new ArgvInput(['farmer', 'test:interactive', '--no-interaction']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $content = ob_get_clean();

        expect($code)->toBe(0);
        expect($content)->toContain('Mode: non-interactive');
    });

    it('handles --quiet by silencing standard output', function () {
        $input = new ArgvInput(['farmer', 'test:simple', '--quiet']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $content = ob_get_clean();

        expect($code)->toBe(0);
        expect($content)->toBeEmpty();
    });

    it('executes global middleware pipes when registered via through', function () {
        $this->kernel->through(DummySamplePipe::class);

        $input = new ArgvInput(['farmer', 'test:simple']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $content = ob_get_clean();

        expect($code)->toBe(0);
        expect($content)->toContain('Global Pipe Before');
        expect($content)->toContain('Simple command executed');
        expect($content)->toContain('Global Pipe After');
    });

    it('executes command-specific middleware pipes declared on command class', function () {
        $this->kernel->register(DummyCommandWithPipes::class);

        $input = new ArgvInput(['farmer', 'test:with-pipes']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $content = ob_get_clean();

        expect($code)->toBe(0);
        expect($content)->toContain('Command Pipe Before');
        expect($content)->toContain('Command With Pipes Executed');
        expect($content)->toContain('Command Pipe After');
    });

    it('executes command-specific middleware pipes attached fluently to closure commands via through', function () {
        $this->kernel->command('test:closure-pipe', function (Output $output) {
            $output->line('Closure Handler Executed');
            return 0;
        })->through(DummyCommandSpecificPipe::class);

        $input = new ArgvInput(['farmer', 'test:closure-pipe']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $content = ob_get_clean();

        expect($code)->toBe(0);
        expect($content)->toContain('Command Pipe Before');
        expect($content)->toContain('Closure Handler Executed');
        expect($content)->toContain('Command Pipe After');
    });

    it('executes global pipes and command pipes in the correct onion order', function () {
        $this->kernel->through(DummySamplePipe::class);
        $this->kernel->register(DummyCommandWithPipes::class);

        $input = new ArgvInput(['farmer', 'test:with-pipes']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $content = ob_get_clean();

        expect($code)->toBe(0);
        
        $expectedOrder = [
            'Global Pipe Before',
            'Command Pipe Before',
            'Command With Pipes Executed',
            'Command Pipe After',
            'Global Pipe After',
        ];

        $pos = -1;
        foreach ($expectedOrder as $line) {
            $newPos = strpos($content, $line);
            expect($newPos)->toBeGreaterThan($pos);
            $pos = $newPos;
        }
    });

    it('allows registering and executing custom global option handlers', function () {
        $customHandler = new class implements \Potager\Contracts\Console\GlobalOptionHandler {
            public function getOption(): InputOption
            {
                return InputOption::make('custom-flag')->flag()->description('Custom global flag');
            }

            public function handle(InputInterface $input, Output $output, Command $command): ?int
            {
                if ($input->hasOption('custom-flag') && $input->option('custom-flag')) {
                    $output->line('Custom Global Option Executed');
                    return 0;
                }
                return null;
            }
        };

        $this->kernel->getGlobalOptionsManager()->register($customHandler);

        $input = new ArgvInput(['farmer', 'test:simple', '--custom-flag']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $content = ob_get_clean();

        expect($code)->toBe(0);
        expect($content)->toContain('Custom Global Option Executed');
        expect($content)->not->toContain('Simple command executed');
    });
});
