---
outline: deep
---

# Console Commands

Farmer is the command-line interface included with Potager. It allows you to run built-in framework tasks, generate boilerplate code, and create your own custom console commands.

```bash
php farmer <command> [arguments] [options]
```

## Running Commands

To list all available console commands:

```bash
php farmer list
```

To display the help screen and available options for any command:

```bash
php farmer make:model --help
```

---

## Writing Commands

### Generating Command Classes

To create a new command class, use the `make:command` generator:

```bash
php farmer make:command SendWeeklyNewsletterCommand
```

This creates a new class in `app/Commands/SendWeeklyNewsletterCommand.php`:

```php
namespace App\Commands;

use Potager\Console\Command;
use Potager\Console\IO\InputArgument;
use Potager\Console\IO\InputOption;
use Potager\Console\IO\Output;
use Potager\Contracts\Console\InputInterface;

class SendWeeklyNewsletterCommand extends Command
{
    /**
     * The name of the command.
     */
    protected string $name = 'newsletter:send';

    /**
     * The short description shown in the command list.
     */
    protected string $description = 'Send weekly newsletter emails to subscribers';

    /**
     * Define the command arguments.
     */
    protected function getArguments(): array
    {
        return [
            InputArgument::make('subscriber')
                ->optional()
                ->description('Optional specific subscriber email'),
        ];
    }

    /**
     * Define the command options.
     */
    protected function getOptions(): array
    {
        return [
            InputOption::make('force')
                ->shortcut('f')
                ->flag()
                ->description('Force sending without confirmation'),
        ];
    }

    /**
     * Execute the command.
     */
    public function handle(InputInterface $input, Output $output): int
    {
        $subscriber = $input->argument('subscriber') ?? 'all';
        $force = $input->option('force');

        $output->info("  Dispatching newsletter to: {$subscriber}");
        $output->success('  Newsletter sent successfully!');

        return 0;
    }
}
```

### Registering Commands

Register your command in `commands/commands.php` using the `Farmer` facade:

```php
use App\Commands\SendWeeklyNewsletterCommand;
use Potager\Facades\Farmer;

Farmer::register(SendWeeklyNewsletterCommand::class);
```

---

## Closure Commands

For simple tasks, you can define closure-based commands directly in `commands/commands.php`:

```php
use Potager\Console\IO\InputArgument;
use Potager\Console\IO\InputOption;
use Potager\Console\IO\Output;
use Potager\Contracts\Console\InputInterface;
use Potager\Facades\Farmer;

Farmer::command('greet:user', function (InputInterface $input, Output $output): int {
    $name = $input->argument('name') ?? 'World';
    $message = "Hello, {$name}!";

    if ($input->option('loud')) {
        $message = strtoupper($message);
    }

    $output->success($message);
    return 0;
})
    ->description('Greet a user by name')
    ->withInputDefinition([
        InputArgument::make('name')->optional()->description('Name of the user'),
        InputOption::make('loud')->shortcut('L')->flag()->description('Uppercase greeting'),
    ]);
```

---

## Defining Arguments & Options

### Arguments (`InputArgument`)

```php
use Potager\Console\IO\InputArgument;

// Required argument
InputArgument::make('user_id')->required()->description('The user ID');

// Optional argument with default value
InputArgument::make('format')->default('json')->description('Export format');

// Array / multiple values argument
InputArgument::make('files')->multiple()->description('List of file paths');
```

### Options (`InputOption`)

```php
use Potager\Console\IO\InputOption;

// Boolean Flag (-f or --force)
InputOption::make('force')->shortcut('f')->flag()->description('Force execution');

// Option requiring a value (--type=csv)
InputOption::make('type')->requireValue()->description('Report type');

// Repeatable option (--tag=v1 --tag=core)
InputOption::make('tag')->shortcut('t')->multiple()->description('Tags');
```

---

## Command Output

The `Potager\Console\IO\Output` helper provides styled messaging and tables:

```php
public function handle(Output $output): int
{
    // Basic text
    $output->line('Starting process...');

    // Colored alerts
    $output->info('  ℹ Information note');
    $output->success('  ✔ Task completed successfully');
    $output->warning('  ⚠ Low disk space');
    $output->error('  ✖ An error occurred');

    // Tables
    $output->table(
        ['ID', 'Name', 'Role'],
        [
            ['1', 'Alice', 'Admin'],
            ['2', 'Bob', 'Member'],
        ]
    );

    return 0;
}
```

---

## Command Middlewares (Pipes)

Pipes allow you to wrap commands with pre- and post-execution logic (benchmarking, security checks, audit logging).

### Creating a Pipe

```bash
php farmer make:pipe ExecutionTimerPipe
```

```php
namespace App\Pipes;

use Potager\Console\Command;
use Potager\Console\IO\Output;
use Potager\Contracts\Console\InputInterface;
use Potager\Contracts\Console\PipeInterface;

class ExecutionTimerPipe implements PipeInterface
{
    public function handle(InputInterface $input, Output $output, Command $command, callable $next): int
    {
        $start = hrtime(true);

        $exitCode = $next($input, $output);

        $ms = round((hrtime(true) - $start) / 1e6, 2);
        $output->line("  ⚡ Execution time: {$ms} ms");

        return $exitCode;
    }
}
```

### Attaching Pipes (`through`)

#### Globally for all commands:
In `commands/commands.php`:

```php
Farmer::through(ExecutionTimerPipe::class);
```

#### On a specific command:
On a command class:
```php
protected array $pipes = [
    ConfirmInProductionPipe::class,
];
```
Or on a closure command:
```php
Farmer::command('db:wipe', fn(...) => ...)->through(ConfirmInProductionPipe::class);
```
