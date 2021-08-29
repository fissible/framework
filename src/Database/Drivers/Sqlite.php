<?php declare(strict_types=1);

namespace Fissible\Framework\Database\Drivers;

use Fissible\Framework\Collection;
use Fissible\Framework\Filesystem\File;
use Clue\React\SQLite\DatabaseInterface;
use Clue\React\SQLite\Factory;
use Clue\React\SQLite\Result;
use Evenement\EventEmitterInterface;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;

class Sqlite extends Driver
{
    protected DatabaseInterface $proxied;

    protected function __construct(EventEmitterInterface|DatabaseInterface $driver)
    {
        $this->proxied = $driver;
        static::$proxiedClass = get_debug_type($driver);
    }

    public static function create(mixed $config = []): ?Driver
    {
        if ($config) {
            static::setConfig($config);
        }

        static::requireConfigKey('path');

        $DbFile = new File(static::$Config->path);
        $info = $DbFile->info();

        if ($info['dirname'] === '.') {
            $DbFile = new File(rtrim(static::$Config->path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'database.sqlite3');
        } elseif (!isset($info['extension'])) {
            $DbFile = new File(static::$Config->path.'.sqlite');
        }

        $factory = new Factory(Loop::get());

        return new static($factory->openLazy($DbFile->getPath()));
    }

    public function count(string $sql, array $params = []): PromiseInterface
    {
        return $this->proxied->query($sql, $params)->then(function (Result $Result) {
            return $Result->rows[0]['COUNT(*)'] ?? 0;
        });
    }

    public function delete(string $sql, array $params = []): PromiseInterface
    {
        return $this->proxied->query($sql, $params)->then(function (Result $Result) {
            return $Result->changed;
        });
    }

    public function first(string $sql, array $params = []): PromiseInterface
    {
        return $this->proxied->query($sql, $params)->then(function (Result $Result) {
            return $Result->rows[0] ?? null;
        }, function (\Exception $error) {
            echo "\n" . 'Error: ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL;
            echo $error->getTraceAsString() . PHP_EOL;
        });
    }

    public function get(string $sql, array $params = []): PromiseInterface
    {
        return $this->proxied->query($sql, $params)->then(function (Result $Result) {
            return new Collection($Result->rows ?? []);
        });
    }

    public function insert(string $sql, array $params = []): PromiseInterface
    {
        return $this->proxied->query($sql, $params)->then(function (Result $Result) {
            if ($Result->changed === 1) {
                return $Result->insertId;
            }
            return $Result->changed;
        });
    }

    public function update(string $sql, array $params = []): PromiseInterface
    {
        return $this->proxied->query($sql, $params)->then(function (Result $Result) {
            return $Result->changed;
        });
    }

    public function query(string $sql, array $params = []): PromiseInterface
    {
        return $this->proxied->query($sql, $params)->then(function (Result $Result) use ($sql) {
            switch (substr($sql, 0, 6)) {
                case 'SELECT':
                    return new Collection($Result->rows ?? []);
                    break;
                case 'INSERT':
                    if ($Result->changed === 1) {
                        return $Result->insertId;
                    }
                case 'UPDATE':
                case 'DELETE':
                    return $Result->changed;
                    break;
            }
        });
    }
}