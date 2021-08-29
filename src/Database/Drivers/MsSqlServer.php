<?php declare(strict_types=1);

namespace Fissible\Framework\Database\Drivers;

class MsSqlServer extends Driver {

    protected int $port = 3306;

    public static function create(mixed $config = []): ?Driver
    {
        if ($config) {
            static::setConfig($config);
        }

        static::requireConfigKey('host|socket');
        static::requireConfigKey('user|username');
        $Server = static::$Config->Server;
        $username = static::$Config->user ?? static::$Config->username ?? null;
        $password = static::$Config->password ?? null;

        if (isset(static::$Config->port)) {
            $Server .= ','.static::$Config->port;
        } elseif (isset(static::$Config->Port)) {
            $Server .= ','.static::$Config->Port;
        }

        return null;
    }
}