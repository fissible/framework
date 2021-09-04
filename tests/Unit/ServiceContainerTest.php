<?php declare(strict_types=1);

namespace Tests\Unit;

use Fissible\Framework\Providers\MailServiceProvider;
use Fissible\Framework\Providers\NotificationServiceProvider;
use Fissible\Framework\ServiceContainer;
use Fissible\Framework\Services\MailService;
use Fissible\Framework\Services\NotificationService;
use Tests\TestCase;

class ServiceContainerTest extends TestCase
{
    public function testBindInstance()
    {
        $Container = new ServiceContainer(static::app());
        $Object = new \stdClass();
        $Object->id = uniqid();
        $Container->bindInstance(\stdClass::class, $Object);

        $Retrieved = $Container->instance(\stdClass::class);

        $this->assertEquals(\stdClass::class, get_debug_type($Retrieved));
        $this->assertEquals($Object->id, $Retrieved->id);
    }

    public function testBootProviders()
    {
        (new MailServiceProvider(static::app()))->register();

        $Providers = [NotificationServiceProvider::class];
        $Container = static::app()->Container();
        $Container->bootProviders($Providers);

        $this->assertTrue($Container->provides(NotificationService::class));

        $Service = $Container->make(NotificationService::class);


        $this->assertEquals(NotificationService::class, get_debug_type($Service));
    }

    public function testDefineProviderMake()
    {
        $Container = new ServiceContainer(static::app());
        $Container->defineProvider(\stdClass::class, function () {
            $Object = new \stdClass();
            $Object->id = time();
            return $Object;
        });

        $Object = $Container->make(\stdClass::class);

        $this->assertEquals(\stdClass::class, get_debug_type($Object));
        $this->assertNotNull($Object->id);
    }

    public function testRegisterProviders()
    {
        $Providers = [MailServiceProvider::class];
        $Container = static::app()->Container();
        $Container->registerProviders($Providers);

        $this->assertTrue($Container->provides(MailService::class));
    }
}