<?php declare(strict_types=1);

namespace Fissible\Framework\Services;

use Fissible\Framework\Models\Email;
use Fissible\Framework\Models\User;

class NotificationService
{
    public function __construct(
        protected MailService $MailService
        ) {}

    public function email(User $User, Email $Email): int
    {
        return $this->MailService->send($Email, $User->email, $User->name_first . ' ' . $User->name_last);
    }
}