<?php declare(strict_types=1);

namespace Fissible\Framework\Database\Drivers;

use Fissible\Framework\Arr;
use Fissible\Framework\Config\Memory;
use Fissible\Framework\Exceptions\ConfigurationException;
use Fissible\Framework\Interfaces\Config;
use Clue\React\SQLite\DatabaseInterface;
use Evenement\EventEmitterInterface;
use React\MySQL\ConnectionInterface;
use React\Promise\PromiseInterface;

class Driver
{
    protected static Config $Config;

    protected static string $proxiedClass;

    protected int $port = 0;

    protected function __construct(EventEmitterInterface $driver)
    {
        $this->proxied = $driver;
        static::$proxiedClass = get_debug_type($driver);
    }

    /**
     * @return Driver
     */
    public static function create(mixed $config = []): ?Driver
    {
        if ($config) {
            static::setConfig($config);
        }

        static::requireConfigKey('driver');

        switch (static::$Config->driver) {
            case 'mysql':
                return Mysql::create(static::$Config);
            break;
            // case 'pgsql':
            // case 'postgres':
            //     return Postgres::create($Config);
            // break;
            case 'sqlite':
            case 'sqlite3':
                return Sqlite::create(static::$Config);
            break;
            // case 'sqlsrv':
            //     return MsSqlServer::create($Config);
            // break;
        }
    }

    public static function setConfig(array|\stdClass|Config|null $config = [])
    {
        $data = new \stdClass;

        if (is_null($config)) {
            $config = [];
        }

        if (!is_array($config)) {
            if ($config instanceof \stdClass) {
                $config = Arr::fromObject($config);
            } elseif ($config instanceof Config) {
                $config = Arr::fromObject($config->getData());
            } else {
                throw new \InvalidArgumentException('Configuration must be an array, \stdClass, or Config instance.');
            }
        }

        foreach ($config as $key => $value) {
            $data->$key = $value;
        }

        static::$Config = new Memory($data);
    }

    public static function __callStatic($name, $args)
    {
        $instance = static::create();
        if ($name == 'select') {
            return $instance->internalSelect(...$args);
        } elseif (method_exists($instance, $name)) {
            return call_user_func_array(array($instance, $name), $args);
        }

        return call_user_func_array([static::$proxiedClass, $name], $args);
    }

    public function count(string $sql, array $params = []): PromiseInterface
    {
        throw new \Exception('Not implemented.');
    }

    public function delete(string $sql, array $params = []): PromiseInterface
    {
        throw new \Exception('Not implemented.');
    }

    public function exec(string $sql): PromiseInterface
    {
        return $this->query($sql);
    }

    public function first(string $sql, array $params = []): PromiseInterface
    {
        throw new \Exception('Not implemented.');
    }

    public function get(string $sql, array $params = []): PromiseInterface
    {
        throw new \Exception('Not implemented.');
    }

    public function insert(string $sql, array $params = []): PromiseInterface
    {
        throw new \Exception('Not implemented.');
    }

    public function update(string $sql, array $params = []): PromiseInterface
    {
        throw new \Exception('Not implemented.');
    }

    public function query(string $sql, array $params = []): PromiseInterface
    {
        throw new \Exception('Not implemented.');
    }

    public function _proxied()
    {
        return $this->proxied;
    }

    public function __call($name, $args)
    {
        if ($name === 'select') {
            return $this->internalSelect(...$args);
        }

        return call_user_func_array([$this->proxied, $name], $args);
    }

    public function __toString(): string
    {
        return $this->toSql();
    }

    /**
     * Throw an exception if a config key is missing.
     * 
     * @param string $key
     * @return void
     */
    protected static function requireConfigKey(string $key): void
    {
        if (!isset(static::$Config)) {
            throw new ConfigurationException('', $key);
        }

        if (false !== strpos($key, '|')) {
            $ors = explode('|', $key);
            $exists = array_filter(array_map(function ($key) {
                return static::$Config->has($key);
            }, $ors));

            if (count($exists) < 1) {
                throw new ConfigurationException('', $key);
            }
        } elseif (false !== strpos($key, ',')) {
            $ands = explode(',', $key);
            foreach ($ands as $key) {
                static::requireConfigKey($key);
            }
        } else {
            if (!static::$Config->has($key)) {
                throw new ConfigurationException('', $key);
            }
        }
    }
}