<?php declare(strict_types=1);

namespace Fissible\Framework\Database;

use Fissible\Framework\Database\Drivers\Driver;
use Fissible\Framework\Models\Model;
use React\Promise;

class PaginatedQuery
{
    private static Driver $db;

    private string $className;

    private ?int $pages;

    private int $perPage = 0;

    private $query;

    private ?int $total;

    public function __construct(string $classNameOrTable = null, int $perPage = 0, Driver $db = null)
    {
        if ($db) {
            static::setDriver($db);
        }

        if (is_subclass_of($classNameOrTable, Model::class)) {
            $this->className = $classNameOrTable;
            $this->query();
        } else {
            $this->query($classNameOrTable);
        }

        $this->perPage($perPage);
    }

    public static function driver(): ?Driver
    {
        if (isset(static::$db)) {
            return static::$db;
        }
    }

    public static function setDriver(Driver $db)
    {
        static::$db = $db;
    }

    public static function table(string $table, int $perPage = 0): PaginatedQuery
    {
        return new static($table, $perPage);
    }

    public function limit(int $perPage = 0): PaginatedQuery
    {
        $this->perPage = $perPage;
        return $this;
    }

    public function pages(): Promise\PromiseInterface
    {
        if (!isset($this->pages)) {
            return $this->total()->then(function ($total) {
                if ($this->perPage) {
                    $this->pages = (int) ceil($total / $this->perPage);
                } else {
                    $this->pages = 1;
                }

                return $this->pages;
            });
        }

        return Promise\resolve($this->pages);
    }

    public function perPage(int $limit = 0): PaginatedQuery
    {
        $this->perPage = $limit;
        return $this;
    }

    private function query(string $table = null): Query|Model
    {
        if (!isset($this->query)) {
            if (isset($this->className)) {
                $this->query = call_user_func($this->className.'::query');
            } else {
                if (!$table) throw new \RuntimeException('PaginatedQuery requires a Model class name or table name.');
                $this->query = Query::table($table);
            }
        } else {
            $this->total = null;
            $this->pages = null;
        }
        
        return $this->query;
    }

    public function total()
    {
        if (!isset($this->total)) {
            return $this->query()->count()->then(function ($count) {
                $this->total = $count;

                return $count;
            });
        }

        return Promise\resolve($this->total);
    }

    public function get(int $page = 1): Promise\PromiseInterface
    {
        if ($page < 1) throw new \InvalidArgumentException('Page cannot be less than 1.');

        // $this->total();
        // return $this->total()->then(function ($total) use ($page) {
            if ($this->perPage) {
                $this->query()->limit($this->perPage);
                if ($offset = ($page * $this->perPage) - $this->perPage) {
                    $this->query()->offset($offset);
                }
            }

            return $this->query()->get();

        // }, function (\Exception $error) {
        //     echo 'Error: ' . $error->getMessage() . PHP_EOL;
        // });
    }

    public function pageData(): Promise\PromiseInterface
    {
        return $this->total()->then(function ($total) {
            return $this->pages()->then(function ($pages) use ($total) {
                return [
                    'total' => $total,
                    'pages' => $pages,
                    'perPage' => $this->perPage
                ];
            });
        });
        
    }

    public function __call($name, $arguments)
    {
        $this->total = null;
        $this->pages = null;

        return call_user_func_array(array($this->query(), $name), $arguments);
    }
}