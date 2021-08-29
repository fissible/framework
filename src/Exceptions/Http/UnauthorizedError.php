<?php declare(strict_types=1);

namespace Fissible\Framework\Exceptions\Http;

use Exception;

class UnauthorizedError extends ServerError
{
    public function __construct(Exception $previous = null)
    {
        parent::__construct('Unauthorized', 401, $previous);
    }
}