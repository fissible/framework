<?php declare(strict_types=1);

namespace Tests\Unit;

use Fissible\Framework\ServiceContainer;
use Tests\TestCase;

class ServiceContainerTest extends TestCase
{
    public function testBindInstance()
    {
        $Container = new ServiceContainer();
        $Object = new \stdClass();
        $Object->id = uniqid();
        $Container->bindInstance(\stdClass::class, $Object);

        $Retrieved = $Container->instance(\stdClass::class);

        $this->assertEquals(\stdClass::class, get_debug_type($Retrieved));
        $this->assertEquals($Object->id, $Retrieved->id);
    }

    public function testDefineProviderMake()
    {
        $Container = new ServiceContainer();
        $Container->defineProvider(\stdClass::class, function () {
            $Object = new \stdClass();
            $Object->id = time();
            return $Object;
        });

        $Object = $Container->make(\stdClass::class);

        $this->assertEquals(\stdClass::class, get_debug_type($Object));
        $this->assertNotNull($Object->id);
    }
}