<?php declare(strict_types=1);

namespace Fissible\Framework\Http\Middleware;

use Fissible\Framework\Application;
use Fissible\Framework\Http\Middleware\Middleware;
use Fissible\Framework\Http\Request;
use Fissible\Framework\Str;
use Psr\Http\Message\ServerRequestInterface;

class RequestDecoratorMiddleware extends Middleware
{
    public function __invoke(ServerRequestInterface $request, $next)
    {
        $request = new Request($request);
        $contentType = $request->contentType();

        if ($contentType !== 'application/json' && $contentType !== 'application/vnd.api+json') {
            $request->Session()->begin();
            $request->setCurrentLocation();

            // Set a CSRF token in the session
            if (!$request->expectsJson() && !$request->Session()->token()) {
                $request->Session()->token(Str::simpleRandom(16));
            }
        }

        Application::singleton()->Request = $request;

        return $next($request);
    }
}