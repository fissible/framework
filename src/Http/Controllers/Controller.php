<?php declare(strict_types=1);

namespace Fissible\Framework\Http\Controllers;

use Fissible\Framework\Http\Request;
use Fissible\Framework\Http\Response;

class Controller
{
    public function __invoke(Request $request)
    {
        $body = '';

        if ($request->getUri()->getPath() === '/ping') {
            $body = 'PONG';
        }

        return Response::make($body, 200);
    }
}