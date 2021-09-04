<?php declare(strict_types=1);

namespace Fissible\Framework\Services;

use Fissible\Framework\Application;
use Fissible\Framework\Auth\Jwt;
use Fissible\Framework\Models\User;
use Fissible\Framework\Session;
use Psr\Http\Message\ServerRequestInterface;

class AuthService
{
    public static string $redirectTo = '/';

    public static function authenticate(User $User, string $password): bool
    {
        return static::verify($password, $User->password);
    }

    public static function getClaims(ServerRequestInterface $request): ?\stdClass
    {
        if ($token = static::extractToken($request)) {
            return Jwt::decodeToken($token);
        }

        return null;
    }

    public static function hash(string $data): string
    {
        return password_hash($data, PASSWORD_DEFAULT);
    }

    public static function login(User $User, Session $Session)
    {
        if (!$User->verified_at) {
            throw new \Exception('User email unverified.');
        }

        $Session->set('user_id', $User->id);
        $Session->set('User', $User);
    }

    public static function createToken(User $User, int $lifetimeMinutes = null)
    {
        $lifetimeMinutes ??= (int) Application::singleton()->config()->get('security.tokenLifetime') ?? 15;

        return new Jwt(['user_id' => $User->id], $lifetimeMinutes);
    }

    /**
     * Get the JWT token from the Authorization header.
     */
    protected static function extractToken(ServerRequestInterface $request): ?string
    {
        $authHeader = $request->getHeader('Authorization');

        if (!empty($authHeader) && preg_match("/Bearer\s+(.*)$/i", $authHeader[0], $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}