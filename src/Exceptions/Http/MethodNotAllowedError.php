<?php declare(strict_types=1);

namespace Fissible\Framework\Exceptions\Http;

use Exception;

class MethodNotAllowedError extends ServerError
{
    public function __construct($method, $url, Exception $previous = null)
    {
        $message = sprintf('"%s": not allowed on target resource "%s"', $method, $url);
        parent::__construct($message, 405, $previous);
    }
}