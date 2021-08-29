<?php declare(strict_types=1);

namespace Fissible\Framework\Auth;

use Fissible\Framework\Application;
use Firebase\JWT\JWT as FirebaseJWT;

class Jwt
{
    public \stdClass $claims;

    public function __construct(array $claims = [], int $lifetimeMinutes = 900)
    {
        $this->claims = new \stdClass();
        $this->claims->exp = time() + (60 * $lifetimeMinutes);
        $this->claims->iat = time();

        foreach ($claims as $key => $value) {
            $this->claims->$key = $value;
        }
    }

    public function __toString(): string
    {
        return Jwt::createToken($this->claims);
    }

    public static function createToken(\stdClass|array $payload): string
    {
        $key = static::getPrivateKey();
        $algorithm = static::getKeyAlgorithm();
        
        return FirebaseJWT::encode($payload, $key, $algorithm);
    }

    public static function decodeToken(string $token): \stdClass
    {
        $key = static::getPublicKey();
        $algorithm = static::getKeyAlgorithm();
        
        return FirebaseJWT::decode($token, $key, [$algorithm]);
    }

    public static function getPrivateKey(): string
    {
        return Application::singleton()->config()->get('security.privateKey');
    }

    public static function getPublicKey(): string
    {
        return Application::singleton()->config()->get('security.publicKey');
    }

    public static function getKeyAlgorithm(): string
    {
        return Application::singleton()->config()->get('security.keyAlgorithm');
    }
}