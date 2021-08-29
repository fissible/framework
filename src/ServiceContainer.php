<?php declare(strict_types=1);

namespace Fissible\Framework;

class ServiceContainer
{
    protected array $instances;

    protected array $providers;

    /**
     * @param string $class
     * @param mixed $instance
     * @return void
     */
    public function bindInstance(string $class, mixed $instance): void
    {
        if (!isset($this->instances)) {
            $this->instances = [];
        }

        $this->instances[$class] = $instance;
    }

    /**
     * @param string $class
     * @param mixed $provider
     * @return void
     */
    public function defineProvider(string $class, callable $provider): void
    {
        if (!isset($this->providers)) {
            $this->providers = [];
        }

        $this->providers[$class] = $provider;
    }

    /**
     * Check if the container has an instance of the specified class.
     */
    public function has(string $class): bool
    {
        if (isset($this->instances)) {
            return array_key_exists($class, $this->instances);
        }
        return false;
    }

    /**
     * Attempt to resolve an instance of the provided class name.
     * 
     * @param string $class
     * @return mixed
     */
    public function instance(string $class): mixed
    {
        if ($this->has($class)) {
            return $this->instances[$class];
        }

        return null;
    }

    /**
     * Resolve an instance of class.
     * 
     * @param string $class
     * @return mixed
     */
    public function make(string $class): mixed
    {
        if ($this->provides($class)) {
            // create new instance
            if (is_callable($this->providers[$class])) {
                return $this->providers[$class]($this);
            }
        }

        return null;
    }

    /**
     * Check if the container has a provider for the specified class.
     */
    public function provides(string $class): bool
    {
        if (isset($this->providers)) {
            return isset($this->providers[$class]);
        }

        return false;
    }
}