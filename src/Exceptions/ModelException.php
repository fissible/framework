<?php declare(strict_types=1);

namespace Fissible\Framework\Exceptions;

use Exception;

class ModelException extends Exception
{
    public function __construct(string $message, $code = 404, Exception $previous = null)
    {
        parent::__construct(sprintf('Model Exception [%d]: %s', $code, $message), $code, $previous);
    }
}
