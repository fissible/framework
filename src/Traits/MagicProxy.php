<?php declare(strict_types=1);

namespace Fissible\Framework\Traits;

trait MagicProxy
{
    protected $proxied;

    protected static $proxiedClass;

    public function _proxied()
    {
        return $this->proxied;
    }

    public function __call($name, $args)
    {
        if (isset($this->proxied)) {
            return call_user_func_array([$this->proxied, $name], $args);
        }
        return null;
    }

    public static function __callStatic($name, $args)
    {
        if (isset(static::$proxiedClass)) {
            return call_user_func_array([static::$proxiedClass, $name], $args);
        }
        return null;
    }

    public function get(string $name, $default = null)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }

        return $default;
    }

    public function push(string $name, array ...$values): self
    {
        $contents = $this->get($name) ?? [];

        if (!is_array($contents)) {
            throw new \InvalidArgumentException('Unable to push a value onto a non-array member.');
        }

        foreach ($values as $value) {
            $contents[$name][] = $value;
        }

        $this->set($name, $contents);

        return $this;
    }

    public function pull(string $name, $default = null)
    {
        $value = $this->get($name, $default);
        $this->remove($name);

        return $value;
    }

    public function remove(string $name)
    {
        unset($this->$name);

        return $this;
    }

    public function set(string $name, $value)
    {
        $this->$name = $value;
    }

    public function __get($name)
    {
        if (false !== strpos($name, '_')) {
            $name = implode(array_map(function (string $part) {
                return ucfirst(strtolower($part));
            }, explode('_', $name)));
        }
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return call_user_func([$this, $method]);
        }

        return $this->get($name);
    }

    public function __isset($name): bool
    {
        return isset($this->$name);
    }

    public function __set(string $name, $value) 
    {
        $this->set($name, $value);
    }
}