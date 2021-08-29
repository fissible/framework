<?php declare(strict_types=1);

namespace Fissible\Framework\Exceptions\Http;

use Exception;

class ServerError extends Exception
{
    public function __construct($message = 'Internal Server Error', int $code = 500, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}