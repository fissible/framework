<?php declare(strict_types=1);

namespace Fissible\Framework\Commands;

use Fissible\Framework\Application;
use Fissible\Framework\Filesystem;
use Fissible\Framework\Input;
use Fissible\Framework\Output;
use React\Promise\PromiseInterface;

final class InstallCommand extends WriteMigrationsCommand
{
    public static string $command = 'install';

    public static string $description = 'Set up a local application: write environment file (.env) and write migration files.';


    public function run(): PromiseInterface
    {
        $this->stdio()->write('Ready to set up the local application, press Ctrl+C to exit.' . PHP_EOL);

        $this->setupEnv();

        $this->checkPrerequisites();

        // Write users table migrations
        $this->creatMigration('Create Users', 'users', <<<EOT
            \$table->int('id')->primary();
            \$table->string('email', 255)->notNull()->unique();
            \$table->string('password', 255);
            \$table->string('verification_code', 255);
            \$table->timestamp('verified_at');
            \$table->string('name_first', 255)->notNull();
            \$table->string('name_last', 255)->notNull();
            \$table->bool('is_closed')->default(false);
            \$table->timestamp('created_at')->default("(strftime('%s','now'))");
            \$table->timestamp('updated_at');
EOT);

        // Write password_reset_tokens table migrations
        $this->creatMigration('Create Password Reset Tokens', 'password_reset_tokens', <<<EOT
            \$table->int('user_id')->notNull();
            \$table->string('token', 128)->notNull()->unique();
            \$table->timestamp('token_expiry')->notNull();
            \$table->primaryKey('user_id', 'token');
EOT);

        // Write email table migrations
        $this->creatMigration('Email', 'email', <<<EOT
            \$table->int('id')->primary();
            \$table->string('to_email', 255);
            \$table->string('to_name', 255);
            \$table->string('from_email', 255);
            \$table->string('from_name', 255);
            \$table->string('subject', 255)->notNull();
            \$table->string('template', 255);
            \$table->text('body', 255);
            \$table->text('variables', 255);
            \$table->timestamp('sent_at');
            \$table->timestamp('created_at')->default("(strftime('%s','now'))");
            \$table->timestamp('updated_at');
EOT);

        $this->stdio()->write('Run database migrations to create new tables.' . PHP_EOL);
        $this->stdio()->end();

        return \React\Promise\resolve(null);
    }

    public function setupEnv()
    {
        $Out = new Output();
        $ROOT_PATH = getcwd() . DIRECTORY_SEPARATOR;
        $envExample = new Filesystem\File($ROOT_PATH . '.env.example');
        $env = new Filesystem\File($ROOT_PATH . '.env');

        if (!$env->exists()) {
            if (!$envExample->exists()) {
                // $this->stdio()->end();
                
                throw new \Exception('.env.example file not found in current working directory.');
            }

            $envExample->copy($env);
        }

        $typed = Input::prompt('test prompt > ', 'eleven');
        $Out->linef('You typed %s', $typed);

        $exampleValues = [];
        $envValues = [];
        
        foreach ($envExample->lines() as $line) {
            [$key, $value] = explode('=', $line);
            $exampleValues[$key] = $value ?? null;
        }

        foreach ($env->lines() as $line) {
            [$key, $value] = explode('=', $line);
            $envValues[$key] = $value ?? null;
        }

        // Prompt for keys in env.example missing from .env
        if ($missing = array_diff_key($exampleValues, $envValues)) {
            foreach ($missing as $key => $value) {
                // $this->stdio()->setPrompt($key . '=');
                // $this->stdio()->addInput($value ?? '');
                // $this->stdio()->on('data', function ($line) use ($key) {
                    $line = Input::prompt($key . '=', $value ?? '');
                    $envValues[$key] = rtrim($line);
                    // $this->stdio()->end();
                // });
            }
        }

        // Confirm remaining values
        foreach ($envValues as $key => $value) {
            if (isset($exampleValues[$key]) && !array_key_exists($key, $missing) && $exampleValues[$key] !== $value) {
                // $this->stdio()->setPrompt($key . '=');
                // $this->stdio()->addInput($value ?? $exampleValues[$key] ?? '');
                // $this->stdio()->on('data', function ($line) use ($key) {
                    $line = Input::prompt($key . '=', $value ?? $exampleValues[$key] ?? '');
                    $envValues[$key] = rtrim($line);
                    // $this->stdio()->end();
                // });
            }
        }

        if (!empty($envValues)) {
            $backup = new Filesystem\File($ROOT_PATH . '.env.backup');
            $env->copy($backup);

            $content = [];
            foreach ($envValues as $key => $value) {
                $content[] = $key . '=' . $value;
            }

            // $this->stdio()->write('WRITE CONTENTS:' . PHP_EOL);
            // $this->stdio()->write(implode("\n", $content) . PHP_EOL);
            $Out->printl('WRITE CONTENTS:');
            $Out->printl(implode("\n", $content));

            // $env->write(implode("\n", $content));

            if ($env->read() === $backup->read()) {
                $backup->delete();
            }

            // $this->stdio()->write('.env file updated.' . PHP_EOL);
            $Out->printl('.env file updated.' . PHP_EOL);
        }
    }
}