<?php declare(strict_types=1);

namespace Fissible\Framework\Http\Middleware;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Fissible\Framework\Exceptions\Http\ServerError;
use Fissible\Framework\Http\JsonResponse;
use Fissible\Framework\Http\Middleware\Middleware;
use Fissible\Framework\Http\Request;
use Fissible\Framework\Http\Response;
use Fissible\Framework\Routing\Route;

class AuthenticationMiddleware extends Middleware
{
    public function __invoke(Request $request, callable $next)
    {
        try {
            $requestMethod = $request->getMethod();
            $requestTarget = $request->getRequestTarget();

            if (!$request->expectsJson()) {
                $request->session()->begin();
            }

            if ($Route = Route::lookup($requestMethod, $request->getUri())) {
                
                // Check for a Route Guard
                if ($Guard = $Route->getGuard()) {
                    return $Guard->validate($request)->then(function ($result) use ($request, $next, $Route, $requestTarget) {
                        if ($result === false) {
                            if ($request->expectsJson()) {
                                return JsonResponse::make('Unauthorized', 401);
                            }

                            $request->Session()->set('intended', $requestTarget);

                            return Response::redirect('/login');
                        }

                        $request = $request->withAttribute('Route', $Route);

                        // Pass through attached middleware
                        // @todo - test
                        // if ($middlewares = $Route->getMiddleware()) {
                        //     foreach ($middlewares as $middleware) {
                        //         $next = Application::singleton()->handleNext($request, $next, $middleware);
                        //     }
                        // }

                        return $next($request);
                    });
                }
            } else {
                if ($request->expectsJson()) {
                    return JsonResponse::make('Not Found', 404);
                }
                return Response::make('404 Not Found', 404);
            }
        } catch (ServerError $e) {
            return JsonResponse::make($e->getMessage(), $e->getCode());
        } catch (SignatureInvalidException $e) {
            return JsonResponse::make($e->getMessage(), 403);
        } catch (ExpiredException $e) {
            return JsonResponse::make($e->getMessage(), 401);
        } catch (\Throwable $e) {
            print $e->getMessage().' in '.$e->getFile(). ':' . $e->getLine().PHP_EOL;
        }

        return $next($request);
    }
}
