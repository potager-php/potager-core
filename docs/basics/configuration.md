---
outline: deep
---

# Configuration

All configuration files for the Potager framework are stored in the `config` directory. Each file returns an array of settings that are automatically loaded and merged during application boot.

---

## Accessing Configuration Values

You can access your configuration values anywhere in your application using the global `config()` helper function.

### Dot-Notation Syntax

Configuration keys use **dot notation**, where the first segment corresponds to the configuration filename (without `.php`), and subsequent segments correspond to array keys within that file:

```php
// Reads 'name' from config/app.php
$appName = config('app.name');

// Reads 'host' from config/database.php -> connections -> mysql -> host
$dbHost = config('database.connections.mysql.host');
```

### Default Values

If the specified configuration value does not exist, a default value will be returned:

```php
$timezone = config('app.timezone', 'UTC');
$debug = config('app.debug', false);
```

### Checking for Configuration Keys

To determine if a configuration key exists:

```php
if (config()->has('services.stripe.secret')) {
    // ...
}
```

---

## Environment Configuration (`.env`)

It is often helpful to have different configuration values based on the environment the application is running in (e.g. local vs production).

Potager uses environment variables managed by `.env` files.

### Reading Environment Variables (`env()`)

Use the `env()` helper function inside your `config/*.php` files to read values from the `.env` file with an optional fallback:

```php
// config/app.php
return [
    'name'        => env('APP_NAME', 'Potager Framework'),
    'environment' => env('APP_ENV', 'production'),
    'debug'       => env('APP_DEBUG', false),
    'url'         => env('APP_URL', 'http://localhost'),
];
```

> **Tip:** You should only call the `env()` function inside configuration files located in the `config/` directory. Throughout the rest of your application, access your settings via `config('...')`.

---

## Example Configuration Files

### `config/app.php`

Defines core application metadata, environment mode, and timezone:

```php
return [
    'name'        => env('APP_NAME', 'Potager App'),
    'version'     => env('APP_VERSION', '1.0.0'),
    'environment' => env('APP_ENV', 'dev'),
    'debug'       => env('APP_DEBUG', true),
    'url'         => env('APP_URL', 'http://localhost:8000'),
    'timezone'    => env('APP_TIMEZONE', 'UTC'),
];
```

### `config/database.php`

Defines database connections used by the Limpid ORM:

```php
return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => env('DB_DATABASE', __DIR__ . '/../database/database.sqlite'),
        ],
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'potager'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset'  => 'utf8mb4',
        ],
    ],
];
```

---

## Setting Configuration at Runtime

You can modify configuration values at runtime (useful during unit tests or custom bootstrap logic):

```php
config()->set('app.timezone', 'Europe/Paris');
config()->set('database.default', 'sqlite');
```
