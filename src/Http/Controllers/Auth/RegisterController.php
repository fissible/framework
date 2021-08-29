<?php declare(strict_types=1);

namespace Fissible\Framework\Http\Controllers\API\v1\Auth;

use Fissible\Framework\Facades\Log;
use Fissible\Framework\Http\Controllers\Controller;
use Fissible\Framework\Http\JsonResponse;
use Fissible\Framework\Http\Request;
use Fissible\Framework\Models\Email;
use Fissible\Framework\Repositories\UserRepository;

class RegisterController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->contentType() !== 'application/json') {
            return JsonResponse::make(['errors' => ['title' => 'Unsupported Media Type']], 415);
        }

        return $request->validate([
            'email' => ['required', 'email', 'not-exists:users,email'],
            'name_first' => ['required', 'string'],
            'name_last' => ['required', 'string']
        ], [
            'email.not-exists' => 'An account with that email address already exists.'
        ])->then(function ($validated) {
            [$data, $errors] = $validated;
            if ($errors) {
                return JsonResponse::make(['errors' => $errors], 422);
            }

            $Repo = new UserRepository();

            return $Repo->create($data)->then(function ($User) {

                // @todo - Move to separate Controller/endpoint? Perhaps the client app should request an email be sent
                $Email = Email::make(
                    'Welcome New User',
                    'Please verify your account here: ' . $_ENV['APP_URL'] . '/auth/verify?code=' . $User->verification_code,
                    'allenmccabe@gmail.com',
                    'API Team'
                )->to($User->email, $User->name_first . ' ' . $User->name_last);

                return $Email->save()->then(function ($Email) use ($User) {
                    $process = new \React\ChildProcess\Process('php ' . SERVER_SCRIPT . ' mail:send ' . $Email->id, ROOT_PATH);
                    $process->start();
                    
                    $process->stdout->on('data', function ($chunk) {
                        Log::info($chunk);
                    });

                    $process->on('exit', function ($exitCode, $termSignal) {
                        if ($exitCode) Log::error('Process exited with code ' . $exitCode);
                    });

                    return JsonResponse::make(['data' => [
                        'type' => 'User',
                        'id' => $User->id,
                        'verification_code' => $User->verification_code
                    ]], 201);
                });
            });
        });
    }
}