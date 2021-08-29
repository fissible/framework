<?php declare(strict_types=1);

namespace Fissible\Framework\Validation;

use React\Promise;

class BooleanRule extends RegexRule
{
    protected string $name = 'boolean';

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" must a boolean.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return Promise\PromiseInterface
     */
    public function passes(string $name, $input): Promise\PromiseInterface
    {
        return $this->resolve($name, $input, in_array($input, [
            true, 1, 'true', '1',
            false, 0, 'false', '0'
        ]));
    }
}