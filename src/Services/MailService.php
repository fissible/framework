<?php declare(strict_types=1);

namespace Fissible\Framework\Services;

use Fissible\Framework\Facades\View;
use Fissible\Framework\Models\Email;
use Fissible\Framework\Str;
use Fissible\Framework\Traits\HasConfig;

class MailService
{
    use HasConfig;

    public function __construct(array|\stdClass|null $config = null)
    {
        $this->setConfig($config);
    }

    public function send(Email $Email, string $to_email, string $to_name = null): int
    {
        $variables = $Email->variables ?? [];
        $body = $Email->body ?? '';

        foreach ($variables as $key => $value) {
            $placeholder = '{{ $' . $key . ' }}';
            if (Str::contains($body, $placeholder)) {
                $body = Str::replace($body, $placeholder, $value);
            }
        }

        $message = new \Swift_Message($Email->subject);
        $message->setFrom([$Email->from_email => $Email->from_name])->setTo($to_email, $to_name);
        
        if ($Email->template) {
            $message->setContentType("text/html");
            $body = View::render('emails.' . $Email->template, [
                'title' => $variables['title'] ?? '',
                'content' => $body
            ]);
        }

        $message->setBody($body);
        
        $successfulRecipientCount = $this->getMailer()->send($message);

        return $successfulRecipientCount;
    }

    private function getTransport()
    {
        $trasport = new \Swift_SmtpTransport(
            $this->config()->get('host'),
            $this->config()->get('port'),
            $this->config()->get('encryption')
        );
        $trasport->setUsername($this->config()->get('username'));
        $trasport->setPassword($this->config()->get('password'));

        return $trasport;
    }

    private function getMailer()
    {
        $transport = $this->getTransport();
        return new \Swift_Mailer($transport);
    }
}