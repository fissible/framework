<?php declare(strict_types=1);

namespace Fissible\Framework\Http\Middleware;

use Fissible\Framework\Exceptions\Http\ServerError;
use Fissible\Framework\Facades\Log;
use Fissible\Framework\Http\JsonResponse;
use Fissible\Framework\Http\Middleware\Middleware;
use Fissible\Framework\Http\Request;
use Fissible\Framework\Routing\Router;

class RouteMiddleware extends Middleware
{
    public function __invoke(Request $request)
    {
        try {
            return Router::dispatch($request);

        } catch (ServerError $e) {
            Log::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
            return JsonResponse::make($e->getMessage(), $e->getCode());
        }

        return JsonResponse::make('Internal Server Error', 500);
    }
}