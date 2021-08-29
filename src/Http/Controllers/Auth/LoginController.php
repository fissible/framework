<?php declare(strict_types=1);

namespace Fissible\Framework\Http\Controllers\API\v1\Auth;

use Fissible\Framework\Http\Controllers\Controller;
use Fissible\Framework\Http\JsonResponse;
use Fissible\Framework\Http\Request;
use Fissible\Framework\Repositories\UserRepository;
use Fissible\Framework\Services\AuthService;

class LoginController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->contentType() !== 'application/json') {
            return JsonResponse::make(['errors' => ['title' => 'Unsupported Media Type']], 415);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'password' => ['required', 'string']
        ]);

        return $validated->then(function ($validation) {
            [$data, $errors] = $validation;
            if ($errors) {
                return JsonResponse::make(['errors' => $errors], 422);
            }

            return UserRepository::getForLogin($data['email'])->then(function ($User) use ($data) {
                if (AuthService::authenticate($User, $data['password'])) {
                    if ($User->verified_at) {
                        $token = AuthService::createToken($User);

                        return JsonResponse::make([
                            'access_token' => (string) $token,
                            'token_type' => 'Bearer',
                            'expires_in' => $token->claims->exp - time()
                        ]);
                    } else {
                        return JsonResponse::make(['errors' => ['authentication' => 'User email unverified.']], 403);
                    }
                } else {
                    return JsonResponse::make(['errors' => ['authentication' => 'Invalid email or password.']], 401);
                }
            });
        });
    }
}