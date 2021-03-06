<?php declare(strict_types=1);

namespace Fissible\Framework\Commands;

use Fissible\Framework\Database\Query;
use Fissible\Framework\Facades\DB;
use Fissible\Framework\Filesystem\Directory;
use Fissible\Framework\Filesystem\File;
use React\Promise\PromiseInterface;

final class DbRollbackCommand extends Command
{
    public static string $command = 'db:rollback';

    public static string $description = 'Run database SQL migration rollbacks.';

    /**
     * Command arguments configuration
     */
    public static $arguments = [
        'batches' => []
    ];


    public function run(): PromiseInterface
    {
        $this->requireDatabase();

        $batches = $this->argument('batches') ?? 0;

        return Query::table('migrations')->orderBy('batch', 'desc')->get()->then(function ($Migrations) use ($batches) {
            $MigrationsDirectory = new Directory($this->app->getMigrationsDirectoryPath($this->app->config()->get('ROOT_PATH')));
            
            if ($Migrations->empty()) {
                $this->stdio()->write('Nothing to roll back.' . PHP_EOL);
            }

            $batchStop = -1;
            foreach ($Migrations as $migration) {
                $batch = intval($migration['batch']);

                if ($batches > 0 && $batchStop < 0) {
                    $batchStop = max($batch - $batches, 0);
                }

                if ($batch <= $batchStop) {
                    break;
                }

                $filename = $migration['migration'] . '.php';
                $MigrationFile = new File($MigrationsDirectory->path($filename));

                if (!$MigrationFile->exists()) {
                    $this->stdio()->write('Error: file for migration ' . $migration['migration'] . ' not found.' . PHP_EOL);
                    return;
                }

                $this->stdio()->write('Rolling back ' . $MigrationFile->getFilename() . '...' . PHP_EOL);

                require $MigrationFile->getPath();

                $fp = fopen($MigrationFile->getPath(), 'r');
                $class = $buffer = '';
                $i = 0;
                while (!$class) {
                    if (feof($fp)) break;

                    $buffer .= fread($fp, 512);
                    $tokens = token_get_all($buffer);

                    if (strpos($buffer, '{') === false) continue;

                    for (; $i < count($tokens); $i++) {
                        if ($tokens[$i][0] === T_CLASS) {
                            for ($j = $i + 1; $j < count($tokens); $j++) {
                                if ($tokens[$j] === '{') {
                                    $class = $tokens[$i + 2][1];
                                }
                            }
                        }
                    }
                }

                $class = '\\' . $class;

                $MigrationClass = new $class;
                $MigrationClass->down();

                Query::table('migrations')->where('id', $migration['id'])->delete()->then(function ($result) use ($MigrationFile) {
                    if ($result) {
                        $this->stdio()->write('Rolled back ' . $MigrationFile->getFilename() . '.' . PHP_EOL);
                    }
                })->done();
            }
        })->then(function () {
            $this->stdio()->end();
            DB::quit();
        }); 
    }
}