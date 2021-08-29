<?php declare(strict_types=1);

namespace Tests\Unit\Routing;

use Fissible\Framework\Auth\Jwt;
use Fissible\Framework\Http\Request;
use Fissible\Framework\Routing\Guard;
use React\Http\Message\ServerRequest;
use Tests\TestCase;

class GuardTest extends TestCase
{
    public function testValidate()
    {
        $this->app()->config()->set('security.publicKey', '12345678910');
        $this->app()->config()->set('security.privateKey', '12345678910');
        $this->app()->config()->set('security.keyAlgorithm', 'HS256');

        $Guard = new Guard(['user_id' => ['required']]);

        $claims = new \stdClass();
        $claims->exp = time() - 1000;
        $claims->user_id = 17;
        $Token = new Jwt((array) $claims);
        $request = new Request(new ServerRequest('POST', '/auth'));
        $request = $request->withHeader('Authorization', 'Bearer ' . $Token);

        $Guard->validate($request)->then(function ($result) {
            $this->assertFalse($result);
        });
        

        $claims = new \stdClass();
        $claims->exp = time() + 5;
        $Token = new Jwt((array) $claims);
        $request = $request->withHeader('Authorization', 'Bearer ' . $Token);

        $Guard->validate($request)->then(function ($result) {
            $this->assertFalse($result);
        });

        $claims = new \stdClass();
        $claims->exp = time() + 5;
        $claims->user_id = null;
        $Token = new Jwt((array) $claims);
        $request = $request->withHeader('Authorization', 'Bearer ' . $Token);

        $Guard->validate($request)->then(function ($result) {
            $this->assertFalse($result);
        });

        $claims = new \stdClass();
        $claims->exp = time() + 50;
        $claims->user_id = 17;
        $Token = new Jwt((array) $claims);
        $request = $request->withHeader('Authorization', 'Bearer ' . $Token);

        $Guard->validate($request)->then(function ($result) {
            $this->assertTrue($result);
        });
    }
}