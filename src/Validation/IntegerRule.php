<?php declare(strict_types=1);

namespace Fissible\Framework\Validation;

use React\Promise;

class IntegerRule extends RegexRule
{
    protected string $name = 'int';

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" must an integer.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return Promise\PromiseInterface
     */
    public function passes(string $name, $input): Promise\PromiseInterface
    {
        return $this->resolve($name, $input, filter_var($input, FILTER_VALIDATE_INT) !== false);
    }
}