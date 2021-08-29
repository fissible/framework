<?php declare(strict_types=1);

namespace Fissible\Framework\Validation;

use React\Promise;

class NumberRule extends RegexRule
{
    protected string $name = 'number';

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" must be numeric.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return Promise\PromiseInterface
     */
    public function passes(string $name, $input): Promise\PromiseInterface
    {
        return $this->resolve($name, $input, is_numeric($input));
    }
}