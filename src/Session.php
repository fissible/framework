<?php declare(strict_types=1);

namespace Fissible\Framework;

use Fissible\Framework\Traits\MagicProxy;
use WyriHaximus\React\Http\Middleware\Session as BaseSession;

class Session
{
    use MagicProxy;

    private const SPECIAL_KEYS = [
        'flash' => '_flash',
        'csrf' => '_token'
    ];

    public function __construct(BaseSession $Session = null)
    {
        if ($Session) {
            $this->proxied = $Session;
        }
    }

    public function all(): array
    {
        if (isset($this->proxied)) {
            return $this->proxied->getContents();
        }
        return [];
    }

    public function flash(string $name, $value)
    {
        $contents = $this->get(self::SPECIAL_KEYS['flash']) ?? [];
        $contents = $contents[$name] ?? [];

        if (!is_array($contents)) {
            throw new \InvalidArgumentException('Unable to push a value onto a non-array member.');
        }

        $contents[$name] = $value;

        $this->set(self::SPECIAL_KEYS['flash'], $contents);

        return $this;
    }

    public function get(string $name, $default = null)
    {
        if (in_array($name, array_keys(self::SPECIAL_KEYS))) {
            $name = self::SPECIAL_KEYS[$name];
        }

        if ($name !== self::SPECIAL_KEYS['flash']) {
            $flashed = $this->get(self::SPECIAL_KEYS['flash']) ?? [];

            if (isset($flashed[$name])) {
                $value = $flashed[$name];
                unset($flashed[$name]);
                $this->set(self::SPECIAL_KEYS['flash'], $flashed);

                return $value;
            }
        }

        $contents = $this->all();

        return $contents[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        if (in_array($name, array_keys(self::SPECIAL_KEYS))) {
            $name = self::SPECIAL_KEYS[$name];
        }

        if ($name !== self::SPECIAL_KEYS['flash']) {
            $flashed = $this->get(self::SPECIAL_KEYS['flash']) ?? [];

            if (isset($flashed[$name])) {
                return true;
            }
        }

        $contents = $this->all();

        return isset($contents[$name]);
    }

    public function push(string $name, array ...$values): self
    {
        if (in_array($name, array_keys(self::SPECIAL_KEYS))) {
            $name = self::SPECIAL_KEYS[$name];
        }

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
        if (in_array($name, array_keys(self::SPECIAL_KEYS))) {
            $name = self::SPECIAL_KEYS[$name];
        }

        $value = $this->get($name, $default);
        $this->remove($name);

        return $value;
    }

    public function remove(string $name)
    {
        if (in_array($name, array_keys(self::SPECIAL_KEYS))) {
            $name = self::SPECIAL_KEYS[$name];
        }

        $contents = $this->all();
        if (isset($contents[$name])) {
            unset($contents[$name]);
            $this->proxied->setContents($contents);
        }

        return $this;
    }

    public function set(string $name, $value): static
    {
        if (in_array($name, array_keys(self::SPECIAL_KEYS))) {
            $name = self::SPECIAL_KEYS[$name];
        }

        $contents = $this->all();

        if ($value === null) {
            unset($contents[$name]);
            $this->proxied->setContents($contents);
        } else {
            $this->proxied->setContents(array_merge($contents, [
                $name => $value
            ]));
        }

        return $this;
    }

    public function token(string $value = null)
    {
        if ($value) {
            $this->set(self::SPECIAL_KEYS['csrf'], $value);
        }

        return $this->get(self::SPECIAL_KEYS['csrf']);
    }
}