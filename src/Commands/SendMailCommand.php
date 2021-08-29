<?php

declare(strict_types=1);

namespace Fissible\Framework\Commands;

use Fissible\Framework\Facades\DB;
use Fissible\Framework\Models\Email;
use Fissible\Framework\Services\MailService;
use React\Promise\PromiseInterface;

final class SendMailCommand extends Command
{
    public function run(): PromiseInterface
    {
        $args = func_get_args();
        $app = array_shift($args);
        $EmailId = array_shift($args);

        if (!($app instanceof \Fissible\Framework\Application)) {
            throw new \InvalidArgumentException();
        }

        if (!$app->db()) {
            throw new \Exception('Database not configured.');
        }

        return Email::find($EmailId)->then(function ($Email) use ($EmailId) {
            if ($Email) {
                $MailService = new MailService();
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
