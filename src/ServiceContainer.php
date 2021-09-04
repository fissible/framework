<?php declare(strict_types=1);

namespace Fissible\Framework;

class ServiceContainer
{
    protected array $aliases = [];

    protected array $instances = [];

    protected array $providers = [];
    
    public function __construct(
        protected Application $Application
    ) {}

    /**
     * Bind an instance to the provided class name.
     * 
     * @param string $class
     * @param mixed $instance
     * @return void
     */
    public function bindInstance(string $class, mixed $instance): void
    {
        $this->instances[$class] = $instance;
    }

    /**
     * Invoke the boot() method on each given Provider.
     * 
     * @param array $Providers
     */
    public function bootProviders(array $Providers)
    {
        foreach ($Providers as $providerClass) {
            if (method_exists($providerClass, 'boot')) {
                $Provider = new $providerClass($this->Application);
                $Provider->boot();
            }
        }
    }

    /**
     * @param string $class
     * @param mixed $provider
     * @return void
     */
    public function defineProvider(string $class, callable $provider): void
    {
        $this->providers[$class] = $provider;
    }

    /**
     * Check if the container has an instance of the specified class.
     */
    public function has(string $class): bool
    {
        return array_key_exists($class, $this->instances);
    }

    /**
     * Return an instance of the provided class if it has been bound.
     * 
     * @param string $class
     * @return mixed
     */
    public function instance(string $class): mixed
    {
        if (isset($this->aliases[$class])) {
            $alias = $this->aliases[$class];

            if (isset($this->instances[$alias])) {
                return $this->instances[$alias];
            }
        }

        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        return null;
    }

    /**
     * Instantiate an instance of class.
     * 
     * @param string $class
     * @return mixed
     */
    public function make(string $class, array $parameters = []): mixed
    {
        if (isset($this->aliases[$class])) {
            $class = $this->aliases[$class];
        }

        if ($this->provides($class)) {
            $provider = $this->providers[$class];

            return $provider($this->Application, ...$parameters);
        }

        return null;
    }

    /**
     * @param string $class
     * @param mixed $provider
     * @return void
     */
    public function makes(string $class, callable $provider)
    {
        $this->providers[$class] = $provider;
    }

    /**
     * Check if the container has a provider for the specified class.
     */
    public function provides(string $class): bool
    {
        if (isset($this->aliases[$class])) {
            $class = $this->aliases[$class];
        }

        return isset($this->providers[$class]);
    }

    public function registerProviders(array $Providers)
    {
        foreach ($Providers as $providerClass) {
            $Provider = new $providerClass($this->Application);
            if (method_exists($Provider, 'register')) {
                $Provider->register();
            }
        }
    }

    /**
     * Resolve an instance of class.
     * 
     * @param string $class
     * @return mixed
     */
    public function resolve(string $class): mixed
    {
        if ($this->has($class)) {
            return $this->instance($class);
        }

        if ($this->provides($class)) {
            return $this->make($class);
        }
    }
}