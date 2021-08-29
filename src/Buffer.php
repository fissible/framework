<?php declare(strict_types=1);

namespace Fissible\Framework;

class Buffer
{
    public array $buffer;

    public int $pointer;

    public function __construct(array $buffer = [])
    {
        $this->buffer = $buffer;
        $this->pointer = count($buffer);
    }

    public function clean()
    {
        $this->buffer = [];
        $this->pointer = 0;
    }

    public function collect(string $string): void
    {
        array_splice($this->buffer, $this->pointer, 0, $string);
        // $this->buffer[$this->pointer] = $string;
        $this->pointer++;
    }

    public function delete()
    {
        if ($this->pointer > 0) {
            $this->pointer--;
            array_splice($this->buffer, $this->pointer, 1);
        } else {
            throw new \RangeException();
        }
    }

    public function pointer(): int
    {
        return $this->pointer;
    }

    public function pop(int $count = 1): array
    {
        $removed = [];
        while (!empty($this->buffer) && $count > 0) {
            $removed[] = array_pop($this->buffer);
            $count--;
            $this->pointer--;
        }
        return $removed;
    }

    public function print(string $string): void
    {
        $this->collect($string);
    }

    public function printf(string $format, ...$vars)
    {
        $this->collect(sprintf(rtrim($format), ...$vars));
    }

    public function printl(string $string): void
    {
        $this->collect(rtrim($string) . "\n");
    }

    public function printlf(string $format, ...$vars): void
    {
        $this->collect(sprintf(rtrim($format) . "\n", ...$vars));
    }

    public function seek(int $travel = 0)
    {
        $pointer = $this->pointer;

        $this->pointer = max(0, $this->pointer + $travel, count($this->buffer));

        return $pointer !== $this->pointer;
    }

    public function seekForward(int $travel)
    {
        if ($this->pointer + $travel > count($this->buffer)) {
            throw new \RangeException();
        }

        $pointer = $this->pointer;

        $this->pointer = min($this->pointer + $travel, count($this->buffer));

        return $pointer !== $this->pointer;
    }

    public function seekBackward(int $travel)
    {
        if ($this->pointer - $travel < 0) {
            throw new \RangeException();
        }
        
        $pointer = $this->pointer;

        $this->pointer = max(0, $this->pointer - $travel);

        return $pointer !== $this->pointer;
    }

    public function flush(): array
    {
        $buffer = $this->get();
        $this->clean();

        return $buffer;
    }

    public function get(): array
    {
        return $this->buffer;
    }
}