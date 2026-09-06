---
outline: deep
---

# Getting Started

Welcome to **Potager Framework** — a fast, lightweight, elegant PHP framework designed for modern web applications and APIs.

---

## System Requirements

Before getting started, ensure your local development environment meets the following requirements:

- **PHP 8.2** or higher
- **Composer** package manager
- Common PHP extensions: `pdo`, `mbstring`, `openssl`, `tokenizer`

---

## Installation

You can create a new Potager project using Composer:

```bash
composer create-project potager/potager-app my-app
cd my-app
```

Or install the core framework into an existing project:

```bash
composer require potager/potager-core
```

---

## Directory Structure

A standard Potager application structure is clean, intuitive, and modular:

```text
my-app/
├── app/
│   ├── Commands/       # Custom CLI commands
│   ├── Controllers/    # HTTP controllers
│   ├── Middleware/     # HTTP middlewares
│   ├── Models/         # Limpid ORM models
│   └── Pipes/          # Console middleware pipes
├── bootstrap/
│   └── app.php         # Application bootstrapper
├── config/
│   ├── app.php         # Application name, version, env
│   ├── database.php    # Database connections
│   └── mail.php        # Mail service settings
├── public/
│   └── index.php       # HTTP front controller
├── routes/
│   ├── web.php         # Web routes
│   └── api.php         # API routes
├── commands/
│   └── commands.php    # Console commands & global pipes
├── .env                # Environment variables
├── composer.json       # Project dependencies
└── farmer              # Farmer CLI executable
```

---

## Environment Configuration

Potager uses DotEnv configuration out of the box. Copy the example `.env.example` file to `.env`:

```bash
cp .env.example .env
```

Open `.env` to configure your environment variables:

```ini
APP_NAME="Potager App"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=potager_db
DB_USERNAME=root
DB_PASSWORD=secret
```

---

## Running the Application

### 1. Start the HTTP Development Server

You can start PHP's built-in web server pointing to the `public/` directory:

```bash
php -S localhost:8000 -t public
```

Open your browser and navigate to `http://localhost:8000`.

### 2. Using the Farmer CLI

Potager includes the **Farmer** CLI to help you scaffold code and run tasks:

```bash
php farmer list
```

---

## Next Steps

Now that your project is running, explore the core concepts of the framework:

- [Configuration](/basics/configuration) — Configure settings, databases, and `.env` variables.
- [Routing](/basics/routing) — Define HTTP routes, parameters, and middleware.
- [Models & Database](/basics/models) — Define ORM models using PHP 8 attributes.
- [Console Commands](/console/commands) — Create custom CLI commands and pipes.
