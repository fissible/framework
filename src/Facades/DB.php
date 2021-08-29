<?php declare(strict_types=1);

namespace Fissible\Framework\Facades;

use Fissible\Framework\Database\Drivers\Driver;
use Fissible\Framework\Database\Query;
use Fissible\Framework\Traits\RequiresServiceContainer;

class DB {

    use RequiresServiceContainer;

    public static function instance(string $name = null)
    {
        return self::app()->instance(Driver::class);
    }

    public static function quit()
    {
        return self::app()->instance(Driver::class)->quit();
    }

    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array([Query::class, $name], $arguments);
    }
}