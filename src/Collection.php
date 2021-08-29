<?php declare(strict_types=1);

namespace Fissible\Framework;

use Fissible\Framework\Arr;
use Fissible\Framework\Exceptions\InvalidTypeException;

class Collection implements \IteratorAggregate, \Countable, \JsonSerializable
{
    protected array $set;

    private string $type;

    public function __construct($set = null, string $type = null)
    {
        $this->setType($type);
        $this->setInternal($set);
    }

    public function clear(): void
    {
        $this->set = [];
    }

    public function column($column_key): Collection
    {
        $Collection = new Collection();
        foreach ($this->set as $key => $value) {
            if (is_array($value) && array_key_exists($column_key, $value)) {
                $Collection->push($value[$column_key]);
            } elseif (is_object($value) && isset($value->{$column_key})) {
                $Collection->push($value->{$column_key});
            }
        }

        return $Collection;
    }

    public function contains($filter): bool
    {
        foreach ($this->set as $key => $value) {
            if (is_callable($filter)) {
                if ($filter($value, $key) === true) {
                    return true;
                }
            } elseif ($filter == $value) {
                return true;
            }
        }

        return false;
    }

    public function copy()
    {
        return clone $this;
    }

    public function count(): int
    {
        if (isset($this->set)) {
            return count($this->set);
        }
        return 0;
    }

    public function delete($filter)
    {
        foreach ($this->set as $key => $value) {
            if (is_callable($filter)) {
                if ($filter($value, $key) === true) {
                    unset($this->set[$key]);
                    return true;
                }
            } elseif ($filter == $value) {
                unset($this->set[$key]);
                return true;
            }
        }

        return false;
    }

    public function each(callable $callback)
    {
        foreach ($this->set as $key => $value) {
            $callback($value, $key);
        }
 
        return $this;
    }

    public function empty(): bool
    {
        return $this->count() === 0;
    }

    public function filter(callable $filter): Collection
    {
        $Collection = new Collection();
        $isAssociative = $this->isAssociative();
        foreach ($this->set as $key => $value) {
            if ($filter($value, $key) === true) {
                if ($isAssociative) {
                    $Collection->set($key, $value);
                } else {
                    $Collection->push($value);
                }
            }
        }
        return $Collection;
    }

    public function first(callable $filter = null)
    {
        foreach ($this->set as $key => $value) {
            if (is_null($filter) || $filter($value, $key) === true) {
                return $value;
            }
        }
        return null;
    }

    public function get($key, $default = null)
    {
        if ($this->has($key)) {
            return $this->set[$key];
        }

        if (is_callable($default)) {
            return $default($this);
        }
        return $default;
    }

    public function has($key): bool
    {
        return isset($this->set[$key]);
    }

    public function isAssociative(): bool
    {
        return Arr::isAssociative($this->set);
    }

    public function isNumeric(): bool
    {
        return Arr::isIndexed($this->set);
    }

    /**
     * Get the keys of the internal set.
     */
    public function keys(): array
    {
        return array_keys($this->set);
    }

    public function last(callable $filter = null)
    {
        $set = array_reverse($this->set);
        foreach ($set as $key => $value) {
            if (is_null($filter) || $filter($value, $key) === true) {
                return $value;
            }
        }
        return null;
    }

    public function getIterator()
    {
        if (is_array($this->set)) {
            return new \ArrayIterator($this->set);
        }
        if ($this->set instanceof \Traversable) {
            return $this->set;
        }
    }

    public function jsonSerialize()
    {
        return $this->set;
    }

    public function map(callable $callback)
    {
        $Collection = new Collection();
        foreach ($this->set as $key => $value) {
            $Collection->set($key, $callback($value, $key));
        }
        return $Collection;
    }

    public function merge($iterable): Collection
    {
        if (!is_iterable($iterable)) {
            throw new \InvalidArgumentException();
        }

        $isAssociative = $this->isAssociative();
        $Collection = $this->copy();
        foreach ($iterable as $key => $value) {
            if ($isAssociative) {
                $Collection->set($key, $value);
            } else {
                $Collection->push($value);
            }
        }

        return $Collection;
    }

    public function pop()
    {
        return array_pop($this->set);
    }

    public function pull($filter): Collection
    {
        $Collection = new Collection();
        $isAssociative = $this->isAssociative();
        foreach ($this->set as $key => $value) {
            if ((is_callable($filter) && $filter($value, $key) === true) || $filter === $key) {
                if ($isAssociative) {
                    $Collection->set($key, $value);
                } else {
                    $Collection->push($value);
                }
                unset($this->set[$key]);
            }
        }

        return $Collection;
    }

    public function push($item): int
    {
        $this->validateType($this->getItemType($item));

        $this->set[] = $item;
 
        return $this->count();
    }

    public function reverse()
    {
        return new static(array_reverse($this->set, true));
    }

    public function set($key, $item): void
    {
        $this->validateType($this->getItemType($item));

        $this->set[$key] = $item;
    }

    public function shift()
    {
        return array_shift($this->set);
    }

    public function slice(int $index, int $count = null): Collection
    {
        $preserve_keys = $this->isAssociative();
        return new static(array_slice($this->set, $index, $count, $preserve_keys));
    }

    public function sort(callable $function = null, int $flags = SORT_REGULAR): Collection
    {
        $set = $this->set;
        if (is_null($function)) {
            sort($set, $flags);
        } else {
            uasort($set, $function);
        }

        return new static($set);
    }

    public function take(int $count): Collection
    {
        if ($count < 0) {
            $set = array_slice($this->set, $count);
        } else {
            $set = array_slice($this->set, 0, $count);
        }

        return new static($set);
    }

    public function toArray(): array
    {
        return $this->set;
    }

    public function transform(callable $callback)
    {
        $this->type = '';
        foreach ($this->set as $key => $value) {
            $this->set($key, $callback($value, $key));
        }
        return $this;
    }

    public function type(): ?string
    {
        return $this->type ?? null;
    }

    public function unshift($item): int
    {
        array_unshift($this->set, $item);
        return $this->count();
    }

    /**
     * Return a new Collection with the keys reset to consecutive integers.
     * 
     * @return Collection
     */
    public function values(): Collection
    {
        $Collection = new static();
        foreach ($this->set as $key => $value) {
            $Collection->push($value);
        }

        return $Collection;
    }

    /**
     * @param mixed $item
     * @return string
     */
    protected function getItemType($item): string
    {
        $type = get_debug_type($item);
        if ($type === 'unknown type') {
            throw new \InvalidArgumentException('Unknown type');
        }
        return $type;
    }

    protected function setType(?string $type)
    {
        if ($type !== null && $type !== 'NULL' && (!isset($this->type) || $this->type === '')) {
            $this->type = $type;
        }

        return $this;
    }

    protected function validateType(string $type)
    {
        if ($type === 'null') {
            return;
        }
        if (!isset($this->type) || $this->type === '') {
            $this->setType($type);
        }

        $thisType = $this->type();
        if ($thisType && ($thisType !== $type && !is_subclass_of($type, $thisType, true))) {
            throw new InvalidTypeException($thisType, $type);
        }
    }

    private function setInternal($set = null)
    {
        $this->clear();
        if ($set) {
            if ($set instanceof Collection) {
                $set = $set->toArray();
            } elseif (!is_array($set) && !($set instanceof \Traversable)) {
                throw new \InvalidArgumentException();
            }
            foreach ($set as $k => $value) {
                $this->set($k, $value);
            }
        }
        return $this;
    }
}