<?php declare(strict_types=1);

namespace Fissible\Framework\Http\Controllers\API\v1\Auth;

use Fissible\Framework\Http\Controllers\Controller;
use Fissible\Framework\Http\JsonResponse;
use Fissible\Framework\Http\Request;
use Fissible\Framework\Services\AuthService;

class RefreshController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->contentType() !== 'application/json') {
            return JsonResponse::make(['errors' => ['title' => 'Unsupported Media Type']], 415);
        }

        return $request->user()->then(function ($User) {
            $token = AuthService::createToken($User);

            return JsonResponse::make([
                'access_token' => (string) $token,
                'token_type' => 'Bearer',
                'expires_in' => $token->claims->exp - time()
            ]);
        });
    }
}