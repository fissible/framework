<?php declare(strict_types=1);

namespace Fissible\Framework\Http\Controllers\API\v1\Auth;

use Fissible\Framework\Http\Controllers\Controller;
use Fissible\Framework\Http\JsonResponse;
use Fissible\Framework\Http\Request;
use Fissible\Framework\Repositories\UserRepository;
use Fissible\Framework\Services\AuthService;

class VerifyController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->contentType() !== 'application/json') {
            return JsonResponse::make(['errors' => ['title' => 'Unsupported Media Type']], 415);
        }

        $validated = $request->validate([
            'verification_code' => ['required', 'exists:users,verification_code'],
            'password' => ['required', 'string', 'regex:/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{12,}$/']
        ], [
            'password.regex' => 'Password must at least 12 characters and include a number.'
        ]);

        return $validated->then(function ($validation) {
            [$data, $errors] = $validation;
            if ($errors) {
                return JsonResponse::make(['errors' => $errors], 401);
            }

            return UserRepository::getForVerification($data['verification_code'])->then(function ($User) use ($data) {
                // if ($User->verified_at === null) {
                    $attributes = [
                        'password' => $data['password'],
                        'verified_at' => new \DateTime()
                    ];
                    return UserRepository::update($User, $attributes)->then(function ($User) {
                        $token = AuthService::createToken($User);

                        return JsonResponse::make([
                            'access_token' => (string) $token,
                            'token_type' => 'Bearer',
                            'expires_in' => $token->claims->exp - time()
                        ]);
                    });
                // } else {
                //     return JsonResponse::make(['errors' => ['authentication' => 'User email already verified.']], 403);
                // }
            });
        });
    }
}