<?php declare(strict_types=1);

namespace Fissible\Framework\Commands;

use Fissible\Framework\Application;
use Fissible\Framework\Filesystem;
use Fissible\Framework\Str;
use React\Promise\PromiseInterface;

final class MakeMigrationCommand extends Command
{
    public static string $command = 'make:migration';

    public static string $description = 'Generate a SQL migration file.';


    protected function checkPrerequisites()
    {
        $this->requireDatabase();
    }

    public function run(): PromiseInterface
    {
        // $this->checkPrerequisites();

        $stdio = $this->stdio();
        $stdio->setPrompt('Name of migration > ');

        $stdio->on('data', function ($line) use ($stdio) {
            $line = rtrim($line);

            if ($line === 'quit' || empty($line)) {
                $stdio->end();

                return;
            }

            $filename = preg_replace('/\s+/', '_', trim($line));
            $filename = 'Migration_' . date('Y_m_d_') . $filename . '.php';
            $MigrationsDirectory = new Filesystem\Directory($this->app->getMigrationsDirectoryPath());
            $Migration = new Filesystem\File($MigrationsDirectory->getPath() . '/' . $filename);

            if ($Migration->exists()) {
                $stdio->write($Migration->getPath() . ' already exists.' . PHP_EOL);
            } else {
                $stdio->write('Writing: ' . $filename . ' to ' . $Migration->getDir()->getPath() . PHP_EOL);

                if ($Migration->create()) {
                    $parts = explode('_', Str::prune($filename, '.php'));
                    $className = implode('_', array_map('ucfirst', $parts));
                    $body = <<<EOT
<?php declare(strict_types=1);

use Fissible\Framework\Database\Grammar\Schema;
use Fissible\Framework\Database\Migration;
use React\Promise\PromiseInterface;

class $className extends Migration
{
    public function up(): PromiseInterface
    {
        return Schema::create("", function (\$table) {

        });
    }

    public function down(): PromiseInterface
    {
        return Schema::drop("");
    }
}

EOT;
                    $Migration->write($body);
                    $stdio->write($Migration->getPath() . ' written.' . PHP_EOL);
                } else {
                    $stdio->write('Error writing ' . $Migration->getPath() . PHP_EOL);
                }
            }

            $stdio->end();
        });

        return \React\Promise\resolve(null);
    }
}