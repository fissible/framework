<?php declare(strict_types=1);

namespace Fissible\Framework\Exceptions\Http;

use Exception;

class NotFoundError extends ServerError
{
    public function __construct($url, Exception $previous = null)
    {
        $message = sprintf('"%s": not found', $url);
        parent::__construct($message, 404, $previous);
    }
}