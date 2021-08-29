<?php declare(strict_types=1);

namespace Fissible\Framework\Models;

use Fissible\Framework\Application;

class Email extends Model
{
    protected static string $table = 'email';

    protected array $dates = ['sent_at'];

    public static function make(string $subject, string $body = '', string $from_email = '', string $from_name = ''): static
    {
        return new static([
            'subject' => $subject,
            'body' => $body,
            'from_email' => $from_email,
            'from_name' => $from_name
        ]);
    }

    public function to(string $email, string $name = null): static
    {
        $this->to_email = $email;

        if ($name) {
            $this->to_name = $name;
        }
        
        return $this;
    }
}