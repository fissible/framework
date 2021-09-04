<?php declare(strict_types=1);

namespace Fissible\Framework\Http\Middleware;

use Fissible\Framework\Application;
use Fissible\Framework\Http\Middleware\Middleware;
use Psr\Http\Message\ServerRequestInterface;
use WyriHaximus\React\Http\Middleware\SessionMiddleware as BaseMiddleware;

class SessionMiddleware extends Middleware
{
    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $Cache = Application::singleton()->cache();
        $lifetimeMinutes = Application::singleton()->config()->get('session.lifetime-minutes');
        $expiresIn = $lifetimeMinutes * 60;
        $Middleware = new BaseMiddleware(
            'ApiServerSessionCookie',
            $Cache, // Instance implementing React\Cache\CacheInterface
            [ // Optional array with cookie settings, order matters
                $expiresIn, // expiresIn, int, default
                '/', // path, string, default
                '', // domain, string, default
                false, // secure, bool, default
                false // httpOnly, bool, default
            ],
        );

        return $Middleware($request, $next);
    }
}