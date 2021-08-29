<?php declare(strict_types=1);

namespace Fissible\Framework\Validation;

use React\Promise;

class UrlRule extends RegexRule
{
    protected string $name = 'url';

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" must a URL.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return Promise\PromiseInterface
     */
    public function passes(string $name, $input): Promise\PromiseInterface
    {
        return $this->resolve($name, $input, filter_var($input, FILTER_VALIDATE_URL) !== false);
    }
}