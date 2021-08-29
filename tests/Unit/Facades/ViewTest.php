<?php declare(strict_types=1);

namespace Tests\Unit\Facades;

use Fissible\Framework\Facades\View;
use Jenssegers\Blade\Blade;
use Tests\TestCase;

class ViewTest extends TestCase
{
    public string $views_path;

    public string $cache_path;

    public array $config;

    public function setUp(): void
    {
        $this->views_path = __DIR__;
        $this->cache_path = dirname(dirname(__DIR__)) . '/cache';
        $this->config = [
            'views_path' => $this->views_path,
            'cache_path' => $this->cache_path
        ];
    }

    public function testBlade()
    {
        $View = new View($this->config);
        $Blade = $View->Blade();

        $this->assertEquals(Blade::class, get_debug_type($Blade));
    }

    public function testMake()
    {
        $View = View::make('view', config: $this->config);

        $this->assertEquals(\Illuminate\View\View::class, get_debug_type($View));
    }

    public function testRender()
    {
        $string = View::render('view', ['title' => 'Test Title'], config: $this->config);

        $this->assertStringContainsString('<html', $string);
        $this->assertStringContainsString('Test Title', $string);
    }

    public function tearDown(): void
    {
        foreach (scandir($this->cache_path) as $file) {
            if ($file === "." || $file === "..") {
                continue;
            }

            $path = $this->cache_path . DIRECTORY_SEPARATOR . $file;

            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}