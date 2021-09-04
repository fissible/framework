<?php declare(strict_types=1);

namespace Fissible\Framework\Providers;

use Fissible\Framework\Services\MailService;
use Fissible\Framework\Services\NotificationService;

class NotificationServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->App->makes(NotificationService::class, function ($App) {
            return new NotificationService($App->resolve(MailService::class));
        });
    }
}