<?php declare(strict_types=1);

namespace Fissible\Framework\Commands;

use Fissible\Framework\Application;
use Fissible\Framework\Filesystem;
use Fissible\Framework\Str;
use React\Promise\PromiseInterface;

abstract class WriteMigrationsCommand extends Command
{
    public static string $command = 'install:users';

    public static string $description = 'Generate a SQL migration file.';


    protected function checkPrerequisites()
    {
        $this->requireDatabase();
    }

    protected function creatMigration(string $baseName, string $tableName, string $upString)
    {
        $stdio = $this->stdio();
        $Migration = $this->makeMigration($baseName);

        if ($Migration->exists()) {
            $stdio->write('[error] ' . $Migration->getPath() . ' already exists.' . PHP_EOL);
        } else {
            $stdio->write('Writing: ' . $Migration->getFilename() . ' to ' . $Migration->getDir()->getPath() . PHP_EOL);

            if ($Migration->create()) {

                $this->writeMigration($Migration, $tableName, $upString);

                $stdio->write('[ok] ' . $Migration->getPath() . ' written.' . PHP_EOL);
            } else {
                $stdio->write('[error] Error writing ' . $Migration->getPath() . PHP_EOL);
            }
        }
    }

    protected function makeMigration(string $baseName)
    {
        $baseName = preg_replace('/\s+/', '_', $baseName);
        $baseName = Str::prune($baseName, '.php');
        $filename = 'Migration_' . date('Y_m_d_') . $baseName . '.php';
        $MigrationsDirectory = new Filesystem\Directory($this->app->getMigrationsDirectoryPath());

        return new Filesystem\File($MigrationsDirectory->getPath() . '/' . $filename);
    }

    protected function writeMigration(Filesystem\File $Migration, string $tableName, string $upString)
    {
        $filename = $Migration->getFilename();
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
        return Schema::create('$tableName', function (\$table) {
$upString
        });
    }

    public function down(): PromiseInterface
    {
        return Schema::drop('$tableName');
    }
}

EOT;
        $Migration->write($body);
    }
}