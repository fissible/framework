<?php declare(strict_types=1);

namespace Fissible\Framework\Commands;

use Fissible\Framework\Database\Query;
use Fissible\Framework\Facades\DB;
use React\Promise\PromiseInterface;

final class DbMigrateCommand extends Command
{
    public function run(): PromiseInterface
    {
        $args = func_get_args();
        $app = array_shift($args);

        if (!($app instanceof \Fissible\Framework\Application)) {
            throw new \InvalidArgumentException();
        }

        if (!$app->db()) {
            throw new \Exception('Database not configured.');
        }

        return Query::table('migrations')->addSelect('max(batch) AS batch')->value('batch')->then(function ($batch) use ($app) {
            $batch = (int) $batch;
            $app->getPendingDatabaseMigrations()->then(function ($toBeRun) use ($batch) {
                if (count($toBeRun) > 0) {
                    try {
                        $run = 0;
                        foreach ($toBeRun as $migrationName => $MigrationFile) {
                            $run++;
                            $this->stdio()->write('Migrating ' . $MigrationFile->getFilename() . '...' . PHP_EOL);

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

                            $MigrationClass->up()->then(function () use ($migrationName, $batch, $MigrationFile) {
                                Query::table('migrations')->insert([
                                    'migration' => $migrationName,
                                    'batch' => $batch + 1
                                ])->then(function () use ($MigrationFile) {
                                    $this->stdio()->write('Migrated ' . $MigrationFile->getFilename() . '.' . PHP_EOL);
                                })->done(function () {
                                    $this->stdio()->end();
                                    DB::quit();
                                });
                            })->done();
                        }
                    } catch (\Throwable $e) {
                        $this->stdio()->write('Migration error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
                        $this->stdio()->end();
                        DB::quit();
                    }
                } else {
                    $this->stdio()->write('Nothing to migrate.' . PHP_EOL);
                    $this->stdio()->end();
                    DB::quit();
                }
            })->done();
        });
    }
}