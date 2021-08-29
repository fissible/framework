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
    public function __invoke(Request $request): Promise\PromiseInterface
    {
        try {
            if ($Route = static::resolve($request)) {
                Application::singleton()->Request = $request;

                $Dispatcher = new Dispatcher();
                return $Dispatcher($request, $Route)->then(function ($response) use ($request) {
                    $statusCode = $response->getStatusCode();
                    if ($statusCode < 400) {
                        $request->flushPrevious();
                    }
                });
            }
            
        } catch (\Throwable $e) {
            Log::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
        }

        return Promise\resolve(new Response(404, ['Content-Type' => 'application/json'], 'Not found'));
    }

    public static function resolve(Request $request): ?Route
    {
        $requestMethod = $request->getMethod();
        $requestTarget = $request->getRequestTarget();

        return Route::lookup($requestMethod, $requestTarget);
    }
}