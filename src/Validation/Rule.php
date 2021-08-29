<?php declare(strict_types=1);

namespace Fissible\Framework\Validation;

use React\Promise;

class Rule
{
    protected string $message;

    protected string $name;

    public function __construct(string $name = 'custom', callable $test = null, ?string $message = null)
    {
        if (!isset($this->name)) {
            $this->name = $name;
        }
        if (!is_null($test)) {
            $this->setTest($test);
        }
        if (!is_null($message)) {
            $this->setMessage($message);
        }
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message ?? $this->message();
    }

    /**
     * @return string
     */
    public function message(): string
    {
        return 'Input ":attribute" field failed '.$this->name.' validation rule.';
    }

    public function setMessage($message): self
    {
        $this->message = $message;
        return $this;
    }

    public function setTest(callable $test): self
    {
        $this->test = $test;
        return $this;
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return PromiseInterface
     */
    public function passes(string $name, $input): Promise\PromiseInterface
    {
        if (isset($this->test)) {
            $test = $this->test;
            $this->resolve($name, $input, (bool) $test($input));
        }
        return $this->resolve($name, $input, false);
    }

    protected function resolve(string $name, $input, bool $result): Promise\PromiseInterface
    {
        return Promise\resolve(['Rule' => $this, 'field' => $name, 'input' => $input, 'result' => $result]);
    }

    public function __get($name): ?string
    {
        if ($name === 'name') {
            return $this->name;
        }
        return null;
    }
}