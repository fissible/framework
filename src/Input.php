<?php declare(strict_types=1);

namespace Fissible\Framework;

use Seld\CliPrompt\CliPrompt;

class Input
{
    public static function prompt(string $prompt, mixed $default = null): ?string
    {
        echo static::preparePrompt($prompt, $default);
        return CliPrompt::prompt() ?: $default;
    }

    public static function promptSecret(string $prompt, mixed $default = null): ?string
    {
        echo static::preparePrompt($prompt, $default);
        return CliPrompt::hiddenPrompt() ?: $default;
    }

    private static function preparePrompt(string $prompt, mixed $default = null): string
    {
        if ($default !== null && $default !== '') {
            $suffix = [];
            while (strlen($prompt) > 0 && in_array($prompt[-1], [' ', '?', '>', ':'])) {
                $suffix[] = $prompt[-1];
                $prompt = substr($prompt, 0, -1);
            }

            if (count($suffix) > 0) {
                $prompt .= ' [' . $default . ']';
                $prompt .= implode('', array_reverse($suffix));
            } else {
                $prompt .= $default;
            }
        }

        return $prompt;
    }
}