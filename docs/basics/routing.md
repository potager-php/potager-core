---
outline: deep
---

# Routing

Potager's router allows you to map incoming HTTP requests to controllers and actions. Routes are typically defined in your application's route files using the `Potager\Facades\Route` facade.

---

## Basic Routing

The most basic routes accept a URI path and a controller action array `[ControllerClass::class, 'methodName']`:

```php
use App\Controllers\HomeController;
use App\Controllers\UserController;
use Potager\Facades\Route;

Route::get('/', [HomeController::class, 'index']);
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
```

### Available HTTP Methods

The router supports all standard HTTP verbs:

```php
Route::get($path, [Controller::class, 'action']);
Route::post($path, [Controller::class, 'action']);
Route::put($path, [Controller::class, 'action']);
Route::patch($path, [Controller::class, 'action']);
Route::delete($path, [Controller::class, 'action']);
```

---

## Route Parameters

To capture segments of the URI within your route, define route parameters prefixed with a colon `:`:

```php
Route::get('/users/:id', [UserController::class, 'show']);
Route::get('/posts/:category/:slug', [PostController::class, 'show']);
```

Captured parameters are automatically passed to your controller method:

```php
namespace App\Controllers;

use Potager\Controller;

class UserController extends Controller
{
    public function show(string $id)
    {
        // $id contains the value from :id in the URL
    }
}
```

---

## Named Routes

Named routes allow you to generate URLs or redirects conveniently:

```php
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard');

Route::get('/users/:id', [UserController::class, 'show'])
    ->name('users.show');
```

You can then redirect to named routes using the `Redirect` helper:

```php
use Potager\Router\Redirect;

return Redirect::route('dashboard');
```

---

## Route Middleware

Middleware provides a convenient mechanism for inspecting and filtering HTTP requests entering your application (e.g. authentication, logging, CSRF verification).

### Attaching Middleware (`use`)

Attach one or multiple middlewares to a route using the `use()` method:

```php
use App\Middleware\AuthMiddleware;

// Using a fully-qualified class name
Route::get('/profile', [ProfileController::class, 'edit'])
    ->use(AuthMiddleware::class);

// Using a shorthand alias ('auth' maps to App\Middleware\AuthMiddleware)
Route::get('/settings', [SettingsController::class, 'index'])
    ->use('auth');

// Using multiple middlewares
Route::post('/admin/publish', [AdminController::class, 'publish'])
    ->use(['auth', 'admin']);
```

### Creating Middleware

To create a new HTTP middleware, use the `make:middleware` generator:

```bash
php farmer make:middleware AuthenticateMiddleware
```

Every middleware implements `Potager\Contracts\MiddlewareInterface`:

```php
namespace App\Middleware;

use Potager\Contracts\MiddlewareInterface;
use Potager\Router\HttpContext;
use Potager\Router\Redirect;

class AuthenticateMiddleware implements MiddlewareInterface
{
    public function handle(HttpContext $ctx, callable $next): void
    {
        if (!auth()->check()) {
            Redirect::to('/login');
            return;
        }

        // Proceed to the next middleware / controller
        $next();
    }
}
```

---

## Built-In Route Security & Throttling

### CSRF Protection (`csrf`)

Enable CSRF token verification on state-changing routes (POST, PUT, DELETE) with the `csrf()` helper:

```php
// Enable CSRF with default token lifetime
Route::post('/profile/update', [ProfileController::class, 'update'])
    ->csrf();

// Enable CSRF with a custom token TTL in seconds (e.g. 1 hour)
Route::post('/checkout', [CheckoutController::class, 'submit'])
    ->csrf(true, 3600);
```

### Rate Limiting (`throttle`)

Protect your routes against abuse or brute-force attacks using the `throttle()` helper:

```php
// Limit to 5 attempts per 60 seconds
Route::post('/login', [AuthController::class, 'login'])
    ->throttle(true, 5, 60);

// Default rate limit: 60 requests per minute
Route::get('/api/search', [SearchController::class, 'query'])
    ->throttle();
```

---

## Inspecting Routes (`route:list`)

You can view all registered routes, HTTP methods, paths, and controller actions at any time using the Farmer CLI:

```bash
php farmer route:list
```

Output:

```text
+--------+----------------------+--------------------+--------------------------------+
| Method | Path                 | Name               | Action                         |
+--------+----------------------+--------------------+--------------------------------+
| GET    | /                    | home               | App\Controllers\HomeController |
| GET    | /users/:id           | users.show         | App\Controllers\UserController |
| POST   | /login               | auth.login         | App\Controllers\AuthController |
+--------+----------------------+--------------------+--------------------------------+
```
