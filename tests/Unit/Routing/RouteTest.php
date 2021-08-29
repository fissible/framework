<?php declare(strict_types=1);

namespace Tests\Unit\Routing;

use Fissible\Framework\Collection;
use Fissible\Framework\Exceptions\Http\MethodNotAllowedError;
use Fissible\Framework\Exceptions\Http\NotFoundError;
use Fissible\Framework\Routing\Route;
use Fissible\Framework\Routing\RouteParameter;
use Tests\TestCase;

class RouteTest extends TestCase
{
    public function testGetAction()
    {
        Route::$Table = new Collection();
        Route::get('/', function () {
            return 'Home';
        });

        $Route = Route::table()->first();
        $action = $Route->getAction();

        $this->assertEquals('Home', $action());
    }

    public function testGetId()
    {
        Route::$Table = new Collection();
        Route::get('/', function () {
            return 'Home';
        });

        $Route = Route::table()->first();
        $id = $Route->getId();

        $this->assertEquals('GET:/', $id);
    }

    public function testGetMethod()
    {
        Route::$Table = new Collection();
        Route::get('/', function () {
            return 'Home';
        });

        $Route = Route::table()->first();
        $method = $Route->getMethod();

        $this->assertEquals('GET', $method);
    }

    public function testGetParameters()
    {
        Route::$Table = new Collection();
        Route::get('/', function () {
            return 'Home';
        });

        Route::get('/products/{id}', function () {
            return 'Product';
        });

        $HomeRoute = Route::table()->first();
        $ProductRoute = Route::table()->last();
        $HomeParameters = $HomeRoute->getParameters();
        $ProductParameters = $ProductRoute->getParameters();

        $expected = new Collection();
        $expected->push(new RouteParameter('id'));

        $this->assertEquals(new Collection(), $HomeParameters);
        $this->assertEquals($expected, $ProductParameters);
    }

    public function testMatches()
    {
        $action = ['Controller', 'method'];
        $uri1 = '/products/{name}';
        $uri2 = '/page/{pageId}/user/{userId?}';
        $uri3 = '/business/{businessId}/employee/{employeeId}';
        $Route1 = new Route('GET', $uri1, $action);
        $Route2 = new Route('GET', $uri2, $action);
        $Route3 = new Route('GET', $uri3, $action);

        $this->assertFalse($Route1->matches('/products'));
        $this->assertTrue($Route1->matches('/products/sail-boat'));
        $this->assertFalse($Route2->matches('/page/user/46'));
        $this->assertTrue($Route2->matches('/page/34/user/46'));
        $this->assertTrue($Route2->matches('/page/34/user'));
        $this->assertFalse($Route3->matches('/business/13/employee'));
        $this->assertTrue($Route3->matches('/business/13/employee/64'));
    }

    public function testPregMatch()
    {
        $method = 'GET';
        $action = ['Controller', 'method'];
        $uri1 = '/products/{name}';
        $uri2 = '/page/{pageId}/user/{userId?}';
        $uri3 = '/business/{businessId}/employee/{employeeId}';
        $Route1 = new Route('GET', $uri1, $action);
        $Route2 = new Route('GET', $uri2, $action);
        $Route3 = new Route('GET', $uri3, $action);
        
        $this->assertEquals(['name' => 'sail-boat'], $Route1->pregMatch('/products/sail-boat'));
        $this->assertEquals(['pageId' => '34', 'userId' => '46'], $Route2->pregMatch('/page/34/user/46'));
        $this->assertEquals(['pageId' => '34'], $Route2->pregMatch('/page/34/user'));
        $this->assertEquals(['businessId' => '13', 'employeeId' => '64'], $Route3->pregMatch('/business/13/employee/64'));
    }

    public function testLookup()
    {
        Route::$Table = new Collection();
        Route::get('/products/{name}', ['Controller', 'method']);
        Route::get('/page/{pageId}/user/{userId?}', ['Controller', 'method']);
        Route::get('/business/{businessId}/employee/{employeeId}', ['Controller', 'method']);

        $Route1 = Route::lookup('GET', '/products/Fish-Tank');
        $Route2 = Route::lookup('GET', '/page/24/user/14');
        $Route3 = Route::lookup('GET', '/page/25/user');
        $Route4 = Route::lookup('GET', '/business/45/employee/21');
        
        $this->assertEquals('/products/{name}', $Route1->getUri());
        $this->assertEquals('/page/{pageId}/user/{userId?}', $Route2->getUri());
        $this->assertEquals('/page/{pageId}/user/{userId?}', $Route3->getUri());
        $this->assertEquals('/business/{businessId}/employee/{employeeId}', $Route4->getUri());
    }

    public function testLookupNotFound()
    {
        Route::$Table = new Collection();
        Route::get('/business/{businessId}/employee/{employeeId}', ['Controller', 'method']);

        $this->expectException(NotFoundError::class);

        Route::lookup('GET', '/business/45/employee');
    }

    public function testLookupMethodNotAllowed()
    {
        Route::$Table = new Collection();
        Route::get('/business/{businessId}/employee/{employeeId?}', ['Controller', 'method']);

        $this->expectException(MethodNotAllowedError::class);

        Route::lookup('POST', '/business/45/employee');
    }

    public function tearDown(): void
    {
        Route::$Table = new Collection();
    }
}