<?php declare(strict_types=1);

namespace Fissible\Framework\Providers;

use Fissible\Framework\Providers\ServiceProvider;
use Fissible\Framework\Services\MailService;

class MailServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->App->makes(MailService::class, function ($App) {
            return new MailService($App->config()->mail);
        });
    }
}