<?php declare(strict_types=1);

namespace Fissible\Framework\Models;

use Fissible\Framework\Application;

class Email extends Model
{
    protected static string $table = 'email';

    protected array $dates = ['sent_at'];

    protected static $casts = [
        'variables' => 'array'
    ];

    public static function make(string $subject, string $template, string $body = '', array $variables = [], string $from_email = '', string $from_name = ''): static
    {
        return new static([
            'subject' => $subject,
            'template' => $template,
            'body' => $body,
            'variables' => $variables,
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

    public function setVariablesAttribute($value)
    {
        if (!is_string($value)) {
            $value = json_encode($value);
        }

        $this->setAttribute('variables', $value);
    }

    public function getVariablesAttribute()
    {
        $value = $this->getAttribute('variables');

        if (is_string($value)) {
            return json_decode($value, true);
        }
    }
}