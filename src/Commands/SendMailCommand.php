<?php

declare(strict_types=1);

namespace Fissible\Framework\Commands;

use Fissible\Framework\Facades\DB;
use Fissible\Framework\Models\Email;
use Fissible\Framework\Services\MailService;
use React\Promise\PromiseInterface;

final class SendMailCommand extends Command
{
    public static string $command = 'mail:send';

    public static string $description = 'Send the queued email indicated by the provided email primary key.';

    public static $arguments = [
        'email_id' => [
            'required' => true
        ]
    ];


    public function run(): PromiseInterface
    {
        $this->requireDatabase();

        $EmailId = $this->argument('email_id');

        return Email::find($EmailId)->then(function ($Email) use ($EmailId) {
            if ($Email) {
                $MailService = $this->app->resolve(MailService::class);

                if ($MailService->send($Email, $Email->to_email, $Email->to_name) > 0) {
                    echo "[info] Sending email to " . $Email->to_email . '...' . PHP_EOL;
                    $Email->sent_at = new \DateTime();
                    
                    return $Email->save()->done(function() {
                        echo "[ok] Email sent." . PHP_EOL;
                        DB::quit();
                    });
                }
            } else {
                printf('[error] Email with id %d not found.', $EmailId);
                DB::quit();
            }
        }, function (\Throwable|\Exception $e) {
            echo '[error] '.$e->getMessage . PHP_EOL;
        });
    }
}
