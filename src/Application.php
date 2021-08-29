<?php declare(strict_types=1);

namespace Fissible\Framework;

use Fissible\Framework\Attr\ConfigAttribute;
use Fissible\Framework\Config;
use Fissible\Framework\Database\Drivers\Driver as DatabaseDriver;
use Fissible\Framework\Database\Query;
use Fissible\Framework\Exceptions\ConfigurationException;
use Fissible\Framework\Exceptions\FileNotFoundException;
use Fissible\Framework\Facades\Log;
use Fissible\Framework\Filesystem;
use Fissible\Framework\Http\Middleware\Middleware;
use Fissible\Framework\Http\Request;
use Fissible\Framework\Reporting\Logger;
use Fissible\Framework\Traits\HasConfig;
use Fissible\Framework\Traits\RequiresBinary;
use Fissible\Framework\Traits\SystemInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Cache\ArrayCache;
use React\Promise;

class Application
{
    use HasConfig, RequiresBinary, SystemInterface;

    public Request $Request;

    protected static Application $instance;

    protected static array $commands;

    protected Collection $Middlewares;

    protected bool $inCommand = false;

    private ServiceContainer $Container;

    protected function __construct(bool $inCommand = false, array $config = [])
    {
        $this->inCommand = $inCommand;
        $this->Container = new ServiceContainer();
        $this->configure($config);
    }

    /**
     * Output usage information for registered Application commands.
     */
    public static function commandHelp()
    {
        echo 'Available commands:' . PHP_EOL;
        foreach (static::$commands as $name => $command) {
            echo "\t" . $name;
            if (isset($command->title)) {
                echo $command->title . PHP_EOL;
            }
            if (isset($command->description)) {
                echo ' - ' . $command->description . PHP_EOL;
            }
            echo PHP_EOL;

            // @todo - Command can return arguments
        }

        if (static::singleton(true)->db()) {
            static::singleton(true)->db()->quit();
        }
    }

    /**
     * Run a command.
     */
    public static function runCommand(string $name, array $arguments = []): Promise\PromiseInterface
    {
        $Application = static::singleton(true);
        if (!$Application->hasCommand($name)) {
            throw new \InvalidArgumentException(sprintf('Command "%s" does not exist.', $name));
        }

        $callback = static::$commands[$name];

        return $callback($Application, ...$arguments);
    }

    public static function singleton(bool $inCommand = false, array $config = []): static
    {
        if (!isset(static::$instance)) {
            static::$instance = new static($inCommand, $config);
        }

        return static::$instance;
    }

    public function bindCommand(string $name, callable $callback)
    {
        if (!isset(static::$commands)) {
            static::$commands = [];
        }

        static::$commands[$name] = $callback;
    }

    /**
     * @param string $class
     * @param mixed $instance
     * @return void
     */
    public function bindInstance(string $class, mixed $instance): void
    {
        $this->Container->bindInstance($class, $instance);
    }

    /**
     * Get the Application cache.
     * 
     * @return Cache
     */
    public function cache()
    {
        return $this->Container->instance(Cache::class) ?? null;
    }

    /**
     * Get the service container.
     */
    public function Container(): ServiceContainer
    {
        return $this->Container;
    }

    /**
     * Get the Application database connection.
     * 
     * @return DatabaseDriver
     */
    public function db()
    {
        return $this->Container->instance(DatabaseDriver::class) ?? null;
    }

    /**
     * @param string $class
     * @param mixed $provider
     * @return void
     */
    public function defineProvider(string $class, callable $provider): void
    {
        $this->Container->defineProvider($class, $provider);
    }

    public function getCachePath(): string
    {
        return $this->config()->get('APP_CACHE_PATH', $this->getPath('cache'));
    }

    public function getConfigDirectoryPath(): string
    {
        return dirname($this->getPath('config'));
    }

    public function getMigrationsDirectoryPath(): string
    {
        return $this->config()->get('APP_SQL_MIGRATIONS_PATH', $this->getPath('migrations'));
    }

    public function getPath(string $subpath = '')
    {
        $subpath = trim ($subpath, '/');
        $root = defined('ROOT_PATH') ? ROOT_PATH : $this->config()->get('ROOT_PATH');

        if (strlen($root) > 0) {
            return $root . '/' . $subpath;
        }

        return $root;
    }

    public function getPublicPath(): string
    {
        return $this->config()->get('APP_PUBLIC_PATH', $this->getPath('public'));
    }

    public function getPendingDatabaseMigrations(): Promise\PromiseInterface
    {
        $MigrationsDirectory = new Filesystem\Directory($this->getMigrationsDirectoryPath());
        $migrationFiles = $MigrationsDirectory->files();
        $names = array_map(function (Filesystem\File $Migration) {
            return Str::rprune($Migration->getFilename(), '.php');
        }, $migrationFiles);

        return Query::table('migrations')->whereIn('migration', $names)->get()->then(
            function ($Migrations) use ($migrationFiles) {
                $toBeRun = [];
                if ($Migrations) {
                    $alreadyRun = [];
                    
                    if (!$Migrations->empty()) {
                        $alreadyRun = $Migrations->column('migration')->toArray();
                    }

                    foreach ($migrationFiles as $Migration) {
                        $migrationName = Str::rprune($Migration->getFilename(), '.php');
                        if (!in_array($migrationName, $alreadyRun)) {
                            $toBeRun[$migrationName] = $Migration;
                        }
                    }
                    
                    return $toBeRun;
                }
            }
        );
    }

    /**
     * Return the middleware stack.
     */
    public function handleRequest(): array
    {
        $Middleware = new Collection(null, Middleware::class);
        
        $this->Middlewares->each(function ($middleware) use ($Middleware) {
            $arguments = [];

            $reflectionClass = new \ReflectionClass($middleware);
            $methods = $reflectionClass->getMethods();
            $constructors = array_filter($methods, function ($method) {
                return $method->name === '__construct';
            });

            if (isset($constructors[0])) {
                if ($parameters = $constructors[0]->getParameters()) {
                    if (isset($parameters[0])) {
                        if ($attributes = $parameters[0]->getAttributes()) {
                            foreach ($attributes as $Attribute) {
                                if ($Attribute->getName() === ConfigAttribute::class) {
                                    [$key, $default] = $Attribute->getArguments();
                                    $arguments[] = $this->config()->get($key) ?? $default;
                                }
                            }
                        }
                    }
                }
            }

            $Middleware->push(new $middleware(...$arguments));
        });
        
        return $Middleware->toArray();
    }

    public function handleNext(ServerRequestInterface $request, callable $next, string $name)
    {
        if ($middleware = $this->Middlewares->get($name)) {
            $Middleware = new $middleware();
            return $Middleware($request, $next);
        }

        return $next($request);
    }

    /**
     * Check if a command is defined.
     * 
     * @param string $name
     * @return bool
     */
    public function hasCommand(string $name): bool
    {
        return isset(static::$commands[$name]);
    }

    /**
     * Attempt to resolve an instance of the provided class name.
     * 
     * @param string $class
     * @return mixed
     */
    public function instance(string $class): mixed
    {
        return $this->Container->instance($class);
    }

    /**
     * Resolve an instance of class.
     * 
     * @param string $class
     * @return mixed
     */
    public function make(string $class): mixed
    {
        return $this->Container->make($class);
    }

    /**
     * Register a middleware.
     */
    public function registerMiddleware(string $name, string $middleware): void
    {
        if (!isset($this->Middlewares)) {
            $this->Middlewares = new Collection();
        }

        $this->Middlewares->set($name, $middleware);
    }

    /**
     * Scan the .env file for a file path in "APP_CONFIG" and load into memory.
     */
    protected function configure(array $config): void
    {
        if (isset($_ENV['APP_CONFIG'])) {
            $path = $_ENV['APP_CONFIG'];
            $File = new Filesystem\File($path);

            if (!$File->exists()) {
                throw new FileNotFoundException($path);
            }

            switch ($File->extension) {
                case 'json':
                    $this->setConfig(new Config\Json($path));
                    break;
                case 'php':
                    $this->setConfig(include($path));
                    break;
                default:
                    throw new ConfigurationException(sprintf("'%s': unsupported configuration file format.", $path));
                    break;
            }
        } else {
            $this->setConfig($config);
        }

        if (!$this->config()->has('ROOT_PATH')) {
            $this->config()->set('ROOT_PATH', $this->getPath());
        }

        if (!$this->config()->has('SCRIPT_NAME')) {
            $this->config()->set('SCRIPT_NAME', $_ENV['SCRIPT_NAME'] ?? 'server.php');
        }

        $this->configureCache();
        $this->configureLogging();
        $this->configureSecurityKeys();
        $this->configureDatabase()->then(function ($databaseInitialized) {
            $MigrationsDirectory = new Filesystem\Directory($this->getMigrationsDirectoryPath($this->config()->get('ROOT_PATH')));

            if ($databaseInitialized && !$MigrationsDirectory->empty()) {
                Log::info(sprintf('[ok] Database initialized - ready to run database migrations (%s db:migrate)', $this->config()->get('SCRIPT_NAME')));
            } elseif ($databaseInitialized === false) {
                if (!$this->inCommand) {
                    $this->getPendingDatabaseMigrations()->then(function ($toBeRun) {
                        if (count($toBeRun) > 0) {
                            $FileList = array_map(function (Filesystem\File $File) {
                                return $File->filename;
                            }, $toBeRun);
                            Log::warning(sprintf("[warning] Found %d pending database migrations:\n\t - %s\n", count($toBeRun), implode("\n\t - ", $FileList)));
                            Log::info(sprintf("[info] run '%s db:migrate' before starting server.\n", $this->config()->get('SCRIPT_NAME')));
                            // $stdio = new Stdio(Loop::get());
                            // $stdio->write(sprintf('[warning] Found %d pending database migrations (run %s db:migrate)', count($toBeRun), SCRIPT_NAME));
                            // $stdio->setPrompt('Would you like to run them now? [y/N] > ');

                            // $stdio->on('data', function ($line) use ($stdio) {
                            //     $line = rtrim($line, "\r\n");
                            //     // $stdio->write('Your input: ' . $line . PHP_EOL);
                            //     if (strlen($line) > 0 && strtoupper($line[0]) === 'Y') {
                            //         $Command = new Commands\DbMigrateCommand();
                            //         $Command->run($this)->done();
                            //     }
                            // });
                        }
                    })->done();
                }
            }
        }, function (\Throwable $e) {
            echo $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
        })->done();

        // Set the token lifetime
        if (isset($_ENV['APP_TOKEN_LIFETIME_MINS']) && !empty($_ENV['APP_TOKEN_LIFETIME_MINS'])) {
            $this->config()->set('security.tokenLifetime', $_ENV['APP_TOKEN_LIFETIME_MINS']);
        } else {
            $this->config()->set('security.tokenLifetime', 15);
        }

        $this->configureEmail();
    }

    protected function configureCache()
    {
        $this->bindInstance(Cache::class, new ArrayCache());
    }

    /**
     * Scan configuration and ENV vars for database configuration information.
     */
    protected function configureDatabase(): Promise\PromiseInterface
    {
        $configuration = (array) $this->config()->get('database');
        $env = function ($key, $default = null) {
            $value = $default;
            if (isset($_ENV[$key])) {
                $value = $_ENV[$key];
                if ($value === '') {
                    $value = $default;
                }
            }
            
            return $value;
        };

        // If .env values exist they take priority over a configuration file
        if (isset($_ENV['DB_CONNECTION'], $_ENV['DB_DATABASE'])) {
            $configuration['driver'] =   $env('DB_DRIVER')   ?? $configuration['driver'];
            $configuration['username'] = $env('DB_USERNAME') ?? $configuration['username'] ?? null;
            $configuration['password'] = $env('DB_PASSWORD') ?? $configuration['password'] ?? null;

            switch ($configuration['driver']) {
                case 'mysql':
                    $configuration['host'] =        $env('DB_HOST')     ?? $configuration['host'];
                    $configuration['unix_socket'] = $env('DB_SOCKET')   ?? $configuration['unix_socket'];
                    $configuration['port'] =        $env('DB_PORT')     ?? $configuration['port'];
                    $configuration['dbname'] =      $env('DB_DATABASE') ?? $configuration['dbname'];
                    $configuration['charset'] =     $env('DB_CHARSET')  ?? $configuration['charset'];
                    break;
                case 'pgsql':
                case 'postgres':
                    $configuration['host'] =   $env('DB_HOST')     ?? $configuration['host'] ?? $configuration['hostaddr'];
                    $configuration['port'] =   $env('DB_PORT')     ?? $configuration['port'];
                    $configuration['dbname'] = $env('DB_DATABASE') ?? $configuration['dbname'];
                    break;
                case 'sqlite':
                case 'sqlite3':
                    $configuration['path'] = $env('DB_DATABASE') ?? $configuration['path'];
                    break;
                case 'sqlsrv':
                    $port = $env('DB_PORT') ?? $configuration['port'];
                    $configuration['Server'] =  ($env('DB_HOST')     ?? $configuration['host']) . ($port ? ',' . $port : '');
                    $configuration['Database'] = $env('DB_DATABASE') ?? $configuration['Database'];
                    break;
            }
        }

        if ($configuration) {
            $configuration = array_filter($configuration);

            if (!empty($configuration)) {
                $this->bindInstance(DatabaseDriver::class, DatabaseDriver::create($configuration));
                // Query::app($this);

                return $this->db()->query('SELECT 1 FROM migrations')->then(function () {
                    return false;
                }, function (\Throwable $e) {
                    echo $e->getMessage() . PHP_EOL;
                    $this->db()->exec('CREATE TABLE IF NOT EXISTS migrations (
                        id INTEGER PRIMARY KEY,
                        migration VARCHAR (128) NOT NULL,
                        batch INTEGER NOT NULL DEFAULT 1
                    )')->done();
                    return true;
                });
            }
        }

        return Promise\resolve(null);
    }

    protected function configureEmail()
    {
        $env = function ($key, $default = null) {
            $value = $default;
            if (isset($_ENV[$key])) {
                $value = $_ENV[$key];
                if ($value === '') {
                    $value = $default;
                }
            }

            return $value;
        };

        if (isset($_ENV['MAIL_HOST'])) {
            $this->config()->set('mail.host', $env('MAIL_HOST', 'localhost'));
            $this->config()->set('mail.port', $env('MAIL_PORT', 25));
            $this->config()->set('mail.encryption', $env('MAIL_ENCRYPTION'));
            $this->config()->set('mail.username', $env('MAIL_USERNAME'));
            $this->config()->set('mail.password', $env('MAIL_PASSWORD'));
            $this->config()->set('mail.from.name', $env('MAIL_FROM_NAME'));
            $this->config()->set('mail.from.email', $env('MAIL_FROM_EMAIL'));
        }
    }

    /**
     * Scan configuration for logging configuration information.
     */
    protected function configureLogging()
    {
        $configuration = $this->config()->get('logger');
        $this->defineProvider(Logger::class, function ($app) use ($configuration) {
            return Logger::create($configuration);
        });
    }

    protected function configureSecurityKeys()
    {
        if (isset($_ENV['APP_PRIVATE_KEY']) && !empty($_ENV['APP_PRIVATE_KEY'])) {
            $privateKey = $_ENV['APP_PRIVATE_KEY'];
            if (file_exists($privateKey)) {
                $privateKey = file_get_contents($privateKey);
            }
            $this->config()->set('security.privateKey', $privateKey);
        }

        if (isset($_ENV['APP_PUBLIC_KEY']) && !empty($_ENV['APP_PUBLIC_KEY'])) {
            $publicKey = $_ENV['APP_PUBLIC_KEY'];
            if (file_exists($publicKey)) {
                $publicKey = file_get_contents($publicKey);
            }
            $this->config()->set('security.publicKey', $publicKey);
        }

        if (isset($_ENV['APP_KEY_ALGORITHM']) && !empty($_ENV['APP_KEY_ALGORITHM'])) {
            $this->config()->set('security.keyAlgorithm', $_ENV['APP_KEY_ALGORITHM']);
        }
    }
}