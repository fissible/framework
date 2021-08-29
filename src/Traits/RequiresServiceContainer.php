<?php declare(strict_types=1);

namespace Fissible\Framework\Traits;

use Fissible\Framework\Application;
use Fissible\Framework\ServiceContainer;

trait RequiresServiceContainer
{
    protected static Application $app;

    public static function app()
    {
        return Application::singleton();
    }

    /**
     * Get the service container.
     */
    public function Container(): ServiceContainer
    {
        return $this->app()->Container();
    }
}