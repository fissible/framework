<?php declare(strict_types=1);

namespace Tests\Feature;

use Fissible\Framework\Config\Json;
use Tests\TestCase;

final class ConfigTest extends TestCase
{
    public $config;

    protected function setUp(): void
    {
        $this->config = new Json(sprintf('%s/config.json', __DIR__));
    }

    public function testSetGet()
    {
        $this->config->set('tree', 'green');

        $this->assertEquals('green', $this->config->get('tree'));

        $this->config->set('tree', 'brow');

        $this->assertEquals('brow', $this->config->get('tree'));

        $this->config->set('car.paint', 'red');

        $this->assertEquals('red', $this->config->get('car.paint'));

        $this->config->set('car.paint', 'blue');

        $this->assertEquals('blue', $this->config->get('car.paint'));
    }

    public function testGetDataThroughJsonPointer()
    {
        $services = new Json(dirname(__DIR__).'/ApiSchema/services.json');
        $expected = 'eCFR API';
        $actual = $services->get('eCFR.info.title');

        $this->assertEquals($expected, $actual);
    }

    protected function tearDown(): void
    {
        if ($this->config->exists()) {
            $this->config->delete();
        }
    }
}