<?php declare(strict_types=1);

namespace Fissible\Framework\Validation;

use React\Promise;

class RequiredRule extends Rule
{
    protected string $name = 'required';

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" field is required.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return Promise\PromiseInterface
     */
    public function passes(string $name, $input): Promise\PromiseInterface
    {
        if (is_string($input)) {
            return $this->resolve($name, $input, strlen($input) > 0);
        }
        return $this->resolve($name, $input, !empty($input));
    }
}