<?php declare(strict_types=1);

namespace Fissible\Framework\Validation;

use React\Promise;

class EqualsRule extends Rule
{
    protected string $name = 'equals';

    protected string $value;

    public function __construct(string $value = '')
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function message(): string
    {
        return ('The ":attribute" field is invalid.');
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return Promise\PromiseInterface
     */
    public function passes(string $name, $input): Promise\PromiseInterface
    {
        return $this->resolve($name, $input, $input === $this->value);
    }
}