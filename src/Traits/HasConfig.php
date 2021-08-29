<?php declare(strict_types=1);

namespace Fissible\Framework\Traits;

use Fissible\Framework\Arr;
use Fissible\Framework\Config\JsonPointer;
use Fissible\Framework\Config\Memory;
use Fissible\Framework\Interfaces\Config;
use Fissible\Framework\Exceptions\ConfigurationException;

trait HasConfig
{
    protected Config $Config;

    public function config(): Config
    {
        $this->validateConfigInitialized();

        return $this->Config;
    }

    /**
     * Get a JSON Pointer 
     */
    public function configPointer(JsonPointer $Pointer = null, array $path = []): JsonPointer
    {
        $prefix = $Pointer ? $Pointer->reference : '';
        $key = '$ref';
        $pointer = new \stdClass;
        $pointer->$key = $prefix . implode('/', $path);

        return new JsonPointer($pointer);
    }

    public function isConfigured(): bool
    {
        return isset($this->Config);
    }

    public function setConfig(array|\stdClass|Config|null $config = [])
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

        if (isset($this->defaults)) {
            foreach ($this->defaults as $key => $value) {
                $data->$key = $value;
            }
        }

        foreach ($config as $key => $value) {
            $data->$key = $value;
        }

        $this->Config = new Memory($data);
    }

    /**
     * Throw an exception if a config key is missing.
     * 
     * @param string $key
     * @return void
     */
    protected function requireConfigKey(string $key): void
    {
        if (!isset($this->Config)) {
            throw new ConfigurationException('', $key);
        }

        if (false !== strpos($key, '|')) {
            $ors = explode('|', $key);
            $exists = array_filter(array_map(function ($key) {
                return $this->Config->has($key);
            }, $ors));

            if (count($exists) < 1) {
                throw new ConfigurationException('', $key);
            }
        } elseif (false !== strpos($key, ',')) {
            $ands = explode(',', $key);
            foreach ($ands as $key) {
                $this->requireConfigKey($key);
            }
        } else {
            if (!$this->Config->has($key)) {
                throw new ConfigurationException('', $key);
            }
        }
    }

    private function validateConfigInitialized()
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Configuration not set.');
        }
    }

    public function __get($name)
    {
        if ($name === 'config') {
            return $this->Config;
        }
        throw new \Exception(sprintf("%s: unknown property", $name));
    }
}