<?php declare(strict_types=1);

namespace Fissible\Framework\Database\Drivers;

use Fissible\Framework\Collection;
use Evenement\EventEmitterInterface;
use React\MySQL\ConnectionInterface;
use React\MySQL\Factory;
use React\MySQL\QueryResult;
use React\Promise\PromiseInterface;

class Mysql extends Driver {

    protected ConnectionInterface $proxied;

    protected int $port = 3306;

    protected function __construct(EventEmitterInterface|ConnectionInterface $driver)
    {
        $this->proxied = $driver;
    }

    public static function create(mixed $config = []): ?Driver
    {
        if ($config) {
            static::setConfig($config);
        }

        static::requireConfigKey('host|socket');
        static::requireConfigKey('user|username');
        $username = static::$Config->user ?? static::$Config->username ?? null;
        $password = static::$Config->password ?? null;
        $port = static::$Config->port ?? static::$port ?? null;
        $host = static::$Config->host . ($port ? ':' . $port : '');

        $factory = new Factory();

        return new static($factory->createLazyConnection(
            sprintf('%s:%s@%s/%s', $username, $password, $host, static::$Config->name)
        ));
    }

    public function count(string $sql, array $params = []): PromiseInterface
    {
        return $this->proxied->query($sql, $params)->then(function (QueryResult $Result) {
            return $Result->resultRows[0]['COUNT(*)'] ?? 0;
        });
    }

    public function delete(string $sql, array $params = []): PromiseInterface
    {
        return $this->proxied->query($sql, $params)->then(function (QueryResult $Result) {
            return $Result->affectedRows;
        });
    }

    public function first(string $sql, array $params = []): PromiseInterface
    {
        return $this->proxied->query($sql, $params)->then(function (QueryResult $Result) {
            return $Result->resultRows[0] ?? null;
        });
    }

    public function get(string $sql, array $params = []): PromiseInterface
    {
        return $this->proxied->query($sql, $params)->then(function (QueryResult $Result) {
            return new Collection($Result->resultRows ?? []);
        });
    }

    public function insert(string $sql, array $params = []): PromiseInterface
    {
        return $this->proxied->query($sql, $params)->then(function (QueryResult $Result) {
            if ($Result->affectedRows === 1) {
                return $Result->insertId;
            }
            return $Result->affectedRows;
        });
    }

    public function update(string $sql, array $params = []): PromiseInterface
    {
        return $this->proxied->query($sql, $params)->then(function (QueryResult $Result) {
            return $Result->affectedRows;
        });
    }

    public function query(string $sql, array $params = []): PromiseInterface
    {
        return $this->proxied->query($sql, $params)->then(function (QueryResult $Result) use ($sql) {
            switch (substr($sql, 0, 6)) {
                case 'SELECT':
                    return new Collection($Result->rows ?? []);
                    break;
                case 'INSERT':
                    if ($Result->affectedRows === 1) {
                        return $Result->insertId;
                    }
                case 'UPDATE':
                case 'DELETE':
                    return $Result->affectedRows;
                    break;
            }
        });
    }
}