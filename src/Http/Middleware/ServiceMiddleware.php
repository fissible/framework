<?php declare(strict_types=1);

namespace Fissible\Framework\Http\Middleware;

use Fissible\Framework\Application;
use Fissible\Framework\Http\Middleware\Middleware;
use Fissible\Framework\Http\Request;

class ServiceMiddleware extends Middleware
{
    public function __invoke(Request $request, $next)
    {
        Application::singleton()->bootProviders();

        return $next($request);
    }
}