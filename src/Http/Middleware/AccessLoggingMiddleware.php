<?php declare(strict_types=1);

namespace Fissible\Framework\Http\Middleware;

use Fissible\Framework\Facades\Log;
use Fissible\Framework\Http\JsonResponse;
use Fissible\Framework\Http\Middleware\Middleware;
use Fissible\Framework\Http\Request;
use Psr\Http\Message\ResponseInterface;
use React\Promise;

class AccessLoggingMiddleware extends Middleware
{
    public function __invoke(Request $request, callable $next)
    {
        $promise = Promise\resolve($next($request));
        return $promise->then(function (ResponseInterface $response) use ($request) {
            $serverParams = $request->getServerParams();
            $remoteAddr = $serverParams['REMOTE_ADDR'];
            $requestTime = date('d/M/Y:H:i:s O');
            $requestMethod = $request->getMethod();
            $requestTarget = $request->getRequestTarget();
            $protocol = 'HTTP/' . $request->getProtocolVersion();
            $responseCode = $response->getStatusCode();
            $responseBytes = $response->getBody()->getSize();
            
            Log::info(sprintf('%s - - [%s] "%s %s %s" %d %d -', $remoteAddr, $requestTime, $requestMethod, $requestTarget, $protocol, $responseCode, $responseBytes));

            return $response;

        }, function (\Throwable $e) {
            Log::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());

            return JsonResponse::make($e->getMessage(), 500);
        });
    }
}