<?php declare(strict_types=1);

namespace Fissible\Framework\Http;

use Fissible\Framework\Application;
use Fissible\Framework\Str;
use Psr\Http\Message\StreamInterface;
use React\Http\Message\Response as ReactResponse;
use React\Stream\ReadableStreamInterface;

class Response
{
    public static function make(
        string|ReadableStreamInterface|StreamInterface $body = '',
        int $status = 200,
        string|array $headers = [],
        string $version = '1.1',
        ?string $reason = null): ReactResponse
    {
        $App = Application::singleton();

        $headers = (array) $headers;
        $headers = array_merge((array) $App->config()->get('response-headers'), $headers);

        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'text/html';
        }

        return new ReactResponse($status, $headers, $body, $version, $reason);
    }

    public static function redirect(string $location, int $statusCode = 302)
    {
        if (!Str::startsWith($location, 'http://') && !Str::startsWith($location, 'https://')) {
            $location = sprintf('%s/%s', $_ENV['SERVER_URL'], ltrim($location, '/'));
        }

        return new ReactResponse($statusCode, ['Location' => $location]);
    }
}