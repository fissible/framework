<?php declare(strict_types=1);

namespace Fissible\Framework\Validation;

use React\Promise;

class ArrayRule extends RegexRule
{
    protected string $name = 'array';

    protected array $delimiters = [',', '|'];

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" must an array.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return Promise\PromiseInterface
     */
    public function passes(string $name, $input): Promise\PromiseInterface
    {
        if (is_string($input) && strlen($input) > 1) {
            foreach ($this->delimiters as $delim) {
                if (false !== strpos($input, $delim)) {
                    return $this->resolve($name, $input, true);
                }
            }

            return $this->resolve($name, $input, $input[0] === '[' && $input[-1] === ']');
        }

        return $this->resolve($name, $input, is_array($input));
    }
}