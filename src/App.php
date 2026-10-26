<?php

namespace Potager;

use Composer\Autoload\ClassLoader;
use Potager\Auth\Authenticator;
use Potager\Configuration\Repository;
use Potager\Console\IO\ArgvInput;
use Potager\Console\IO\Output;
use Potager\Container\Container;
use Potager\Contracts\Configuration\RepositoryInterface;
use Potager\Exceptions\Handler;
use Potager\Console\Kernel;
use Potager\Database\Database;
use Potager\Database\Migrator;
use Potager\Limpid\Model;
use Potager\Mailer\MailManager;
use Potager\Router\Request;
use Potager\Router\RequestFactory;
use Potager\Router\Router;
use Potager\Sillon\Schema\SchemaBuilder;
use Throwable;

/**
 * Class App
 *
 * The main application bootstrap class. It acts as a central point to manage services,
 * configurations, and commonly used components via dependency injection.
 *
 * @mixin Container
 */
class App
{
    /**
     * The Potager framework version.
     *
     * @var string
     */
    public const VERSION = '0.0.0';

    /**
     * Singleton instance of the App.
     *
     * @var ?App
     */
    protected static ?App $instance = null;

    /**
     * Service container instance.
     *
     * @var ?Container
     */
    protected ?Container $container = null;

    /**
     * Base path of the project.
     *
     * @var string
     */
    protected string $basePath;

    /**
     * App constructor.
     *
     * Initializes the application instance, service container, base path,
     * configuration repository, service bindings, and error handlers.
     *
     * @param string|null $basePath The base path of the project.
     * @throws \Exception If the container is required but not provided.
     */
    public function __construct(?string $basePath = null)
    {
        static::$instance = $this;

        $this->container = new Container();

        $this->setBasePath($basePath);
        $this->loadConfiguration();
        $this->registerBaseBindings();
        $this->registerHandlers();
    }

    /**
     * Create a new application instance.
     *
     * @param string|null $basePath The base path of the project.
     * @return static
     */
    public static function create(?string $basePath = null): static
    {
        return new static($basePath);
    }

    /**
     * Bootstrap the console kernel and load command files from the specified directory.
     *
     * @param string|null $commandsPath Optional custom directory path containing command definitions.
     * @return self
     */
    public function withCommands(?string $commandsPath = null): self
    {
        /** @var Kernel $kernel */
        $kernel = $this->container->make(Kernel::class);
        $kernel->bootstrap();

        $fullPath = $commandsPath ?? $this->basePath . '/commands';

        if (is_dir($fullPath)) {
            foreach (glob($fullPath . '/*.php') as $file) {
                require_once $file;
            }
        }

        return $this;
    }

    /**
     * Register route files from the specified routes directory.
     *
     * @param string $routesPath The relative or absolute path to the routes directory.
     * @return self
     */
    public function withRouting(string $routesPath = '/routes'): self
    {
        $fullRoutesPath = path($routesPath);

        if (is_dir($fullRoutesPath)) {
            $routeFiles = glob($fullRoutesPath . '/*.php');
            foreach ($routeFiles as $file) {
                require_once $file;
            }
        }

        return $this;
    }

    /**
     * Configure application services using a custom callback.
     *
     * @param callable(Container, self): void $callback Callback receiving the container and application instance.
     * @return self
     */
    public function withServices(callable $callback): self
    {
        // Pass the container and application instance to the developer's callback
        $callback($this->container, $this);

        return $this;
    }

    /**
     * Infer the application base path from environment variables or registered Composer autoloader.
     *
     * @return string
     */
    public static function inferBasePath(): string
    {
        return match (true) {
            isset($_ENV['APP_BASE_PATH']) => $_ENV['APP_BASE_PATH'],
            isset($_SERVER['APP_BASE_PATH']) => $_SERVER['APP_BASE_PATH'],
            default => dirname(array_values(array_filter(
                array_keys(ClassLoader::getRegisteredLoaders()),
                fn(string $path) => ! str_starts_with($path, 'phar://')
            ))[0]),
        };
    }

    /**
     * Set the base path for the application.
     * If no path is provided, the application will attempt to infer it.
     *
     * @param string|null $basePath The base path of the project.
     * @return self
     */
    public function setBasePath(?string $basePath = null): self
    {
        // 1. Define and format path
        $this->basePath = $basePath
            ? rtrim($basePath, '\/')
            : static::inferBasePath();

        // 2. Synchronize with the Container (if already initialized)
        if ($this->container) {
            $this->container->instance('path.base', $this->basePath);
        }

        return $this;
    }

    /**
     * Load configuration files from the config directory and bind them to the container.
     *
     * @return void
     */
    protected function loadConfiguration(): void
    {
        $repository = Repository::fromDirectory($this->basePath . '/config');
        $this->container->instanceIfNotExists('config', $repository);
        $this->container->alias([RepositoryInterface::class, Repository::class], 'config');
    }

    /**
     * Register essential application services as singletons if not already present.
     *
     * @return void
     * @throws \Exception If no container is available.
     */
    protected function registerBaseBindings(): void
    {
        if (!$this->container) {
            throw new \Exception(
                "Failed to register base bindings: The service container is not initialized."
            );
        }

        $this->container->instanceIfNotExists(Session::class, new Session());

        $this->container->singletonIfNotExists(Router::class);
        $this->container->singletonIfNotExists(MailManager::class);

        $this->container->singletonIfNotExists(Request::class, RequestFactory::fromGlobals(...));

        $this->container->singletonIfNotExists(Database::class, function (): Database {
            $config = $this->getConfig()->get('database');
            if (!is_array($config)) {
                throw new \RuntimeException(
                    "Database configuration not found. Please ensure [config/database.php] exists and returns an array."
                );
            }
            return new Database($config);
        });

        $this->container->singletonIfNotExists(SchemaBuilder::class, function (): SchemaBuilder {
            return $this->container->make(Database::class)->getSchemaBuilder();
        });

        $this->container->singletonIfNotExists(Migrator::class, function (): Migrator {
            return new Migrator($this->container->make(Database::class));
        });

        $this->container->singletonIfNotExists(Authenticator::class, function (): Authenticator {
            $config = $this->getConfig()->get('auth');
            return new Authenticator($config);
        });

        Model::setDatabaseResolver(fn() => $this->container->make(Database::class));

        $this->container->singletonIfNotExists(Kernel::class);
    }

    /**
     * Register global error, exception, and shutdown handlers.
     *
     * @return void
     */
    protected function registerHandlers(): void
    {
        if (php_sapi_name() === 'cli') {
            return;
        }

        set_error_handler(function ($serverity, $message, $file, $line): bool {
            /** @var Handler $handler */
            $handler = $this->container->make(Handler::class);
            return $handler->handlePhpErrorAsException($serverity, $message, $file, $line);
        });

        set_exception_handler(function (Throwable $throwable): bool {
            /** @var Handler $handler */
            $handler = $this->container->make(Handler::class);
            return $handler->handleUnhandledException($throwable);
        });

        register_shutdown_function(function (): bool {
            /** @var Handler $handler */
            $handler = $this->container->make(Handler::class);
            return $handler->handleShutdownFatalError();
        });
    }

    /**
     * Retrieve the singleton instance of the App.
     *
     * @throws \RuntimeException If the app has not been initialized yet.
     * @return App
     */
    public static function getInstance(): App
    {
        if (static::$instance === null) {
            throw new \RuntimeException(
                'The application has not been initialized. You must instantiate the App first (usually in public/index.php).'
            );
        }
        return static::$instance;
    }

    /**
     * Handle an incoming HTTP request via the router.
     *
     * @return void
     */
    public function handleRequest(): void
    {
        /** @var Router $router */
        $router = $this->container->make(Router::class);
        $router->handleRequest();
    }

    /**
     * Handle a CLI command execution through the console kernel.
     *
     * @param ArgvInput|null $input The input instance, or null to create from argv.
     * @return int The exit status code.
     */
    public function handleCommand(?ArgvInput $input = null): int
    {
        $input ??= new ArgvInput();
        $output = new Output();

        /** @var Kernel $kernel */
        $kernel = $this->container->make(Kernel::class);
        return $kernel->handle($input, $output);
    }

    /**
     * Get the base path of the project.
     *
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get the service container.
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get the configuration instance.
     *
     * @return RepositoryInterface
     */
    public function getConfig(): RepositoryInterface
    {
        return $this->container->make(RepositoryInterface::class);
    }

    /**
     * Static accessor for the configuration instance.
     *
     * @return RepositoryInterface
     */
    public static function useConfig(): RepositoryInterface
    {
        return static::getInstance()->getConfig();
    }

    /**
     * Get the resolved version of the application or framework.
     *
     * Resolves in cascade:
     * 1. config('app.version') (e.g. from .env APP_VERSION)
     * 2. Composer InstalledVersions metadata
     * 3. Static VERSION constant fallback
     *
     * @return string
     */
    public static function version(): string
    {
        if (static::$instance && static::$instance->container?->has(RepositoryInterface::class)) {
            try {
                $configVersion = static::useConfig()->get('app.version');
                if (!empty($configVersion)) {
                    return (string) $configVersion;
                }
            } catch (\Throwable) {
                // Fallback if config is unavailable
            }
        }

        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                $installed = \Composer\InstalledVersions::getPrettyVersion('potager/potager-core');
                if ($installed !== null) {
                    return $installed;
                }
            } catch (\OutOfBoundsException) {
                // Not installed as distinct composer package
            }
        }

        return static::VERSION;
    }

    /**
     * Get the application name (from config('app.name') or fallback).
     *
     * @param string $default
     * @return string
     */
    public static function name(string $default = 'Potager Framework'): string
    {
        if (static::$instance && static::$instance->container?->has(RepositoryInterface::class)) {
            try {
                $name = static::useConfig()->get('app.name');
                if (!empty($name)) {
                    return (string) $name;
                }
            } catch (\Throwable) {
                // Fallback if config is unavailable
            }
        }

        return $default;
    }

    /**
     * Static accessor for the Router instance.
     *
     * @return Router
     */
    public static function useRouter(): Router
    {
        $container = static::getInstance()->getContainer();
        return $container->make(Router::class);
    }

    /**
     * Static accessor for the Session instance.
     *
     * @return Session
     */
    public static function useSession(): Session
    {
        $container = static::getInstance()->getContainer();
        return $container->make(Session::class);
    }

    /**
     * Static accessor for the MailManager instance.
     *
     * @return MailManager
     */
    public static function useMailer(): MailManager
    {
        $container = static::getInstance()->getContainer();
        return $container->make(MailManager::class);
    }

    /**
     * Static accessor for the LatteEngine instance.
     *
     * @return LatteEngine
     */
    public static function useLatte(): LatteEngine
    {
        $container = static::getInstance()->getContainer();
        return $container->make(LatteEngine::class);
    }

    /**
     * Static accessor for the Database instance.
     *
     * @return Database
     */
    public static function useDatabase(): Database
    {
        $container = static::getInstance()->getContainer();
        return $container->make(Database::class);
    }

    /**
     * Static accessor for the Migrator instance.
     *
     * @return Migrator
     */
    public static function useMigrator(): Migrator
    {
        $container = static::getInstance()->getContainer();
        return $container->make(Migrator::class);
    }

    /**
     * Static accessor for the Authenticator instance.
     *
     * @return Authenticator
     */
    public static function useAuth(): Authenticator
    {
        $container = static::getInstance()->getContainer();
        return $container->make(Authenticator::class);
    }

    /**
     * Dynamically delegate method calls to the service container.
     *
     * @param string $method Method name called.
     * @param array<int|string, mixed> $args Arguments passed to the method.
     * @return mixed The result of the method call on the container.
     * @throws \BadMethodCallException If the method does not exist on the Container.
     */
    public function __call(string $method, array $args): mixed
    {
        $container = $this->getContainer();
        if (method_exists($container, $method)) {
            return $container->$method(...$args);
        }

        throw new \BadMethodCallException("Method {$method} does not exist on App or Container.");
    }

    /**
     * Magic static method handler for dynamic useXyz service accessors and container delegation.
     *
     * Allows static calls like App::useCustomService() to resolve services from the container,
     * or delegates static calls to the container instance.
     *
     * @param string $method Method name called.
     * @param array<int|string, mixed> $args Arguments passed to the method.
     * @return mixed The resolved service instance or method call result.
     * @throws \BadMethodCallException If service or method is undefined.
     */
    public static function __callStatic($method, $args): mixed
    {
        if (str_starts_with($method, 'use')) {
            $service = lcfirst(substr($method, 3));
            $instance = self::getInstance();

            if (!$instance->container->has($service)) {
                throw new \BadMethodCallException("Attempted to resolve unregistered service: [{$service}]. Ensure this service is bound in the container before calling App::use{$service}().");
            }

            return $instance->container->make($service, $args);
        }

        $instance = static::getInstance();
        $container = $instance->getContainer();

        if (method_exists($container, $method)) {
            return $container->$method(...$args);
        }

        throw new \BadMethodCallException("Method {$method} does not exist on App or Container.");
    }
}
