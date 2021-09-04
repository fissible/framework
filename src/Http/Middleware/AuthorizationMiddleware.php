<?php declare(strict_types=1);

namespace Fissible\Framework\Http\Middleware;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Fissible\Framework\Exceptions\Http\ServerError;
use Fissible\Framework\Http\JsonResponse;
use Fissible\Framework\Http\Middleware\Middleware;
use Fissible\Framework\Repositories\UserRepository;
use Fissible\Framework\Services\AuthService;
use Psr\Http\Message\ServerRequestInterface;

class AuthorizationMiddleware extends Middleware
{
    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        try {
            $claims = AuthService::getClaims($request);
            if ($User = UserRepository::getById($claims->user_id)) {
                if ($User->email === 'allenmccabe@gmail.com') {
                    return $next($request);
                }
            }

            return JsonResponse::make('Forbidden', 403);
            
        } catch (ServerError $e) {
            return JsonResponse::make($e->getMessage() . ' Authorization!', $e->getCode());
        } catch (SignatureInvalidException $e) {
            return JsonResponse::make($e->getMessage(), 403);
        } catch (ExpiredException $e) {
            return JsonResponse::make($e->getMessage(), 401);
        }

        return $next($request);
    }
}
