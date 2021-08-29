<?php declare(strict_types=1);

namespace Tests\Unit\Auth;

use Fissible\Framework\Auth\Jwt;
use Tests\TestCase;

final class JwtTest extends TestCase
{
    public function setUp(): void
    {
        $App = \Fissible\Framework\Application::singleton();
        $App->config()->set('security.privateKey', 'r3rg3t');
        $App->config()->set('security.publicKey', 'r3rg3t');
        $App->config()->set('security.keyAlgorithm', 'HS256');
    }

    public function testCreateToken()
    {
        $payload = ['iat' => time(), 'user_id' => 37];
        $token = Jwt::createToken($payload);

        $this->assertNotEmpty($token);
    }

    public function testDecodeToken()
    {
        $payload = ['iat' => time(), 'user_id' => 16];
        $token = Jwt::createToken($payload);

        $payload = Jwt::decodeToken($token);

        $this->assertEquals(time(), $payload->iat);
        $this->assertEquals(16, $payload->user_id);
    }
}