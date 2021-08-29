<?php declare(strict_types=1);

namespace Fissible\Framework\Database\Drivers;

class Postgres extends Driver {

    protected int $port = 5432;

    public static function create(mixed $config = []): ?Driver
    {
        if ($config) {
            static::setConfig($config);
        }

        static::requireConfigKey('host|hostaddr');
        $username = static::$Config->user ?? static::$Config->username ?? null;
        $password = static::$Config->password ?? null;
        
        return null;
    }
}