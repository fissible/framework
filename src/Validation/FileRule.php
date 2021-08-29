<?php declare(strict_types=1);

namespace Fissible\Framework\Validation;

use Fissible\Framework\Filesystem\File;
use React\Promise;

class FileRule extends Rule
{
    protected string $name = 'file';

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" must be a path to a file.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return Promise\PromiseInterface
     */
    public function passes(string $name, $input): Promise\PromiseInterface
    {
        if (is_string($input)) {
            $File = new File($input);

            return $this->resolve($name, $input, $File->exists());
        }

        return $this->resolve($name, $input, false);
    }
}