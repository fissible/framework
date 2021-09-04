<?php declare(strict_types=1);

namespace Fissible\Framework\Routing;

use Fissible\Framework\Application;
use Fissible\Framework\Facades\Log;
use Fissible\Framework\Http\Request;
use Fissible\Framework\Routing\Route;
use React\Http\Message\Response;
use React\Promise;

class Router
{
    public static function dispatch(Request $request): Promise\PromiseInterface
    {
        try {
            if ($Route = static::resolve($request)) {
                $Dispatcher = new Dispatcher();

                return $Dispatcher($request, $Route)->then(function (Response $response) use ($request) {
                    $statusCode = $response->getStatusCode();
                    
                    if ($statusCode > 299 && $statusCode < 500 && $request->hasInput() && $request->hasErrors()) {
                        $request->flashPrevious();
                    }

                    return $response;
                });
            }
            
        } catch (\Throwable $e) {
            Log::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
        }

        return Promise\resolve(new Response(404, ['Content-Type' => 'application/json'], 'Not found'));
    }

    public static function resolve(Request $request): ?Route
    {
        return Route::lookup($request->getMethod(), $request->getUri());
    }
}