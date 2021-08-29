<?php declare(strict_types=1);

namespace Tests\Unit\Routing;

use Fissible\Framework\Facades\View;
use Fissible\Framework\Http\Controllers\Controller;
use Fissible\Framework\Http\Request;
use Fissible\Framework\Models\Model;
use Fissible\Framework\Routing\Dispatcher;
use Fissible\Framework\Routing\Route;
use Fissible\Framework\Routing\Router;
use React\Http\Message\Response as ReactResponse;
use React\Http\Message\ServerRequest;
use React\Promise;
use Tests\TestCase;

class DispatcherTest extends TestCase
{
    public function testGetCallableAndReflection()
    {
        $Dispatcher = new Dispatcher();

        // Closure
        $action = function (callable $action) {
            return 'data';
        };
        [$callable, $Reflection] = $Dispatcher->getCallableAndReflection($action);
        $ReflectionParameter = $Reflection->getParameters()[0];

        $this->assertEquals($action, $callable);
        $this->assertEquals('action', $ReflectionParameter->name);

        // array
        $action = [Controller::class];
        [$callable, $Reflection] = $Dispatcher->getCallableAndReflection($action);
        $ReflectionParameter = $Reflection->getParameters()[0];

        $this->assertEquals([new Controller(), '__invoke'], $callable);
        $this->assertEquals('request', $ReflectionParameter->name);

        // instance
        $action = [Controller::class, '__invoke'];
        [$callable, $Reflection] = $Dispatcher->getCallableAndReflection($action);
        $ReflectionParameter = $Reflection->getParameters()[0];

        $this->assertEquals([new Controller(), '__invoke'], $callable);
        $this->assertEquals('request', $ReflectionParameter->name);

        // instance array
        $action = [new Controller(), '__invoke'];
        [$callable, $Reflection] = $Dispatcher->getCallableAndReflection($action);
        $ReflectionParameter = $Reflection->getParameters()[0];

        $this->assertEquals([new Controller(), '__invoke'], $callable);
        $this->assertEquals('request', $ReflectionParameter->name);

        // instance
        $action = new Controller();
        [$callable, $Reflection] = $Dispatcher->getCallableAndReflection($action);
        $ReflectionParameter = $Reflection->getParameters()[0];

        $this->assertEquals([$action, '__invoke'], $callable);
        $this->assertEquals('request', $ReflectionParameter->name);
    }

    public function testGetResponse()
    {
        $Dispatcher = new Dispatcher();

        // string
        $response = $Dispatcher->getResponse('ok');
        $response->then(function ($response) {
            $this->assertEquals(ReactResponse::class, get_debug_type($response));
            $this->assertEquals('ok', $response->getBody());
        });


        // Illuminate\Contracts\View\View
        $View = View::make('view', ['title' => 'Test Title'], config: [
            'views_path' => dirname(__DIR__) . '/Facades',
            'cache_path' => dirname(dirname(__DIR__)) . '/cache'
        ]);
        $response = $Dispatcher->getResponse($View);
        $response->then(function ($response) {
            $this->assertEquals(ReactResponse::class, get_debug_type($response));
            $this->assertStringContainsString('Test Title', $response->getBody() . '');
        });

        // PromiseInterface<string>
        $response = $Dispatcher->getResponse(Promise\resolve('ok'));
        $response->then(function ($response) {
            $this->assertEquals(ReactResponse::class, get_debug_type($response));
            $this->assertEquals('ok', $response->getBody());
        });

        // PromiseInterface<PromiseInterface<string>>
        $response = $Dispatcher->getResponse(Promise\resolve(Promise\resolve('ok')));
        $response->then(function ($response) {
            $this->assertEquals(ReactResponse::class, get_debug_type($response));
            $this->assertEquals('ok', $response->getBody());
        });

        // ReactResponse
        $response = new ReactResponse(200, [], 'ok');
        $response = $Dispatcher->getResponse($response);
        $response->then(function ($response) {
            $this->assertEquals(ReactResponse::class, get_debug_type($response));
            $this->assertEquals('ok', $response->getBody());
        });
    }

    public function testInvoke()
    {
        $Dispatcher = new Dispatcher();

        // endpoint without parameter
        Route::get('/ping', function (Request $request) {
            return 'PONG';
        });
        $Request = new Request(new ServerRequest('GET', '/ping'));
        $response = $Dispatcher($Request, Router::resolve($Request));
        $response->then(function ($response) {
            $this->assertEquals('PONG', $response->getBody());
        });

        // endpoint with parameter
        Route::get('/data/{value}', function (Request $request, $value) {
            return $value;
        });
        $Request = new Request(new ServerRequest('GET', '/data/foobar'));
        $response = $Dispatcher($Request, Router::resolve($Request));
        $response->then(function ($response) {
            $this->assertEquals('foobar', $response->getBody());
        });
    }

    public function testGetFinalParameters()
    {
        $Dispatcher = new Dispatcher();
        $Model1 = new Model(['foo' => 'bar']);
        $Model2 = new Model(['foo' => 'baz']);

        $Container = $this->app()->Container();
        $Container->bindInstance(Model::class, $Model1);
        $Reflection = new \ReflectionFunction(function (Model $Model, int $id) {
            return [$id, $Model->foo];
        });

        $parameters = $Dispatcher->getFinalParameters($Reflection, [$Model2, 7]);

        $this->assertEquals([$Model2, 7], $parameters);

        $parameters = $Dispatcher->getFinalParameters($Reflection, [7]);

        $this->assertEquals([$Model1, 7], $parameters);
    }
}