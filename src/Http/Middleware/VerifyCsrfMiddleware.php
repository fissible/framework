<?php declare(strict_types=1);

namespace Fissible\Framework\Http\Middleware;

use Fissible\Framework\Http\Middleware\Middleware;
use Fissible\Framework\Http\Request;

class VerifyCsrfMiddleware extends Middleware
{
    public function __invoke(Request $request, callable $next)
    {
        $requestMethod = $request->getMethod();

        if (!$request->expectsJson() && in_array(strtoupper($requestMethod), ['PATCH', 'POST', 'PUT', 'DELETE'])) {
            $presented = $request->input('_csrf');

            if (!$presented || $presented !== $request->Session()->token()) {
                return $request->redirectBackWithErrors(['token' => ['Form stale, please try again.']]);
            }
        }

        return $next($request);
    }
}
