<?php declare(strict_types=1);

namespace Fissible\Framework\Providers;

use Fissible\Framework\Application;

class ServiceProvider
{
    public function __construct(
        protected Application $App
    ) {}
}