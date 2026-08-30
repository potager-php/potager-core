---
outline: deep
---

# Code Generators

Potager includes scaffolding commands (`make:*`) to quickly generate models, controllers, middleware, commands, and pipes with consistent naming and boilerplate code.

---

## Available Generators

| Command | Output Location | Description |
| :--- | :--- | :--- |
| `php farmer make:model <name>` | `app/Models/` | Create a Limpid ORM model. |
| `php farmer make:controller <name>` | `app/Controllers/` | Create an HTTP controller. |
| `php farmer make:middleware <name>` | `app/Middleware/` | Create an HTTP middleware. |
| `php farmer make:command <name>` | `app/Commands/` | Create a console command. |
| `php farmer make:pipe <name>` | `app/Pipes/` | Create a console middleware pipe. |

---

## Sub-Namespaces & Folders

All `make:*` commands automatically support sub-directories. You can use forward slashes (`/`) to place generated classes in sub-namespaces:

```bash
php farmer make:controller Admin/UserController
php farmer make:model Auth/UserProfile
```

This automatically generates the appropriate sub-folders and PHP namespace:

```php
namespace App\Controllers\Admin;

use Potager\Controller;

class UserController extends Controller
{
    public function index()
    {
        //
    }
}
```

---

## Overwriting Existing Files (`--force`)

If a file already exists, Farmer stops execution to prevent accidental data loss. Pass the `-f` (or `--force`) option to overwrite the file:

```bash
php farmer make:controller Admin/UserController --force
```

---

## Creating Custom Generators

You can create your own custom scaffolding commands for your project (e.g. Services, Repositories, DTOs).

### 1. Generate the Generator Command

Pass the `--generator` (or `-g`) flag to `make:command`:

```bash
php farmer make:command MakeRepositoryCommand --generator
```

This creates `app/Commands/MakeRepositoryCommand.php`:

```php
namespace App\Commands;

use Potager\Console\GeneratorCommand;

class MakeRepositoryCommand extends GeneratorCommand
{
    protected string $name = 'make:repository';
    protected string $description = 'Create a new Repository class';
    protected string $type = 'Repository';

    protected function getStub(): string
    {
        return __DIR__ . '/Stubs/repository.stub';
    }

    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace . '\Repositories';
    }
}
```

### 2. Create the Stub Template

Create `app/Commands/Stubs/repository.stub`:

```php
<?php

namespace {{namespace}};

class {{class}}
{
    //
}
```

Variables automatically replaced by Farmer:
- `{{namespace}}` : The target namespace (e.g. `App\Repositories\Admin`)
- `{{class}}` : The class name (e.g. `UserRepository`)
