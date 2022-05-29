<?php declare(strict_types=1);

namespace Tests\Unit;

use Fissible\Framework\Application;
use Fissible\Framework\Reporting\Drivers\NullLogger;
use Fissible\Framework\Reporting\Logger;
use React\Promise;
use React\Promise\PromiseInterface;
use Tests\TestCase;

final class ApplicationTest extends TestCase
{
    public $app;

    public function setUp(): void
    {
        $this->app = Application::singleton(config: [
            'ROOT_PATH' => dirname(dirname(__DIR__))
        ]);
    }

    public function testCommandHelp()
    {
        $this->app->bindCommand(\Fissible\Framework\Commands\MakeMigrationCommand::class, 'make:migration');

        ob_start();
        $this->app->commandHelp();
        $contents = ob_get_clean();

        $this->assertStringContainsString('Available commands:', $contents);
        $this->assertStringContainsString('make:migration', $contents);
    }

    public function testRunCommand()
    {
        $expected = ['foo' => 'bada', 'bar' => 'bing'];
        $Command = new class () extends \Fissible\Framework\Commands\Command {
            public static $arguments = [
                'foo' => [],
                'bar' => []
            ];

            public function run(): PromiseInterface
            {
                $args = $this->arguments();

                return Promise\resolve($args);
            }
        };

        $this->app->bindCommand($Command::class, 'test:command');
        $this->app->runCommand('test:command', array_values($expected))->then(function ($args) use ($expected) {
            $this->assertEquals($expected, $args);
        })->done();
    }

    public function testInstance()
    {
        $this->app->bindInstance(Logger::class, NullLogger::create());

        $Logger = $this->app->instance(Logger::class);

        $this->assertInstanceOf(NullLogger::class, $Logger);
    }
}