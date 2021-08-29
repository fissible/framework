<?php declare(strict_types=1);

namespace Fissible\Framework\Commands;

use Fissible\Framework\Filesystem\File;
use Fissible\Framework\Str;
use React\Promise\PromiseInterface;

final class MakeMigrationCommand extends Command
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
            $destination = dirname(dirname(__DIR__)) . '/migrations/';
            $stdio->write('Writing: ' . $filename . ' to ' . $destination . PHP_EOL);

            $Migration = new File($destination . $filename);

            if ($Migration->exists()) {
                $stdio->write($Migration->getPath() . ' already exists.' . PHP_EOL);
            } elseif ($Migration->create()) {
                $parts = explode('_', Str::prune($filename, '.php'));
                $className = implode('_', array_map('ucfirst', $parts));
                $body = <<<EOT
<?php declare(strict_types=1);

use Fissible\Framework\Database\Migration;
use Fissible\Framework\Database\Query;
use React\Promise\PromiseInterface;

class $className extends Migration
{
    public function up(): PromiseInterface
    {
        return Query::driver()->exec("");
    }

    public function down(): PromiseInterface
    {
        return Query::driver()->exec("");
    }
}

EOT;
                $Migration->write($body);
                $stdio->write($Migration->getPath() . ' written.' . PHP_EOL);
            } else {
                $stdio->write('Error writing ' . $Migration->getPath() . PHP_EOL);
            }

            $stdio->close();
        });

        return \React\Promise\resolve(null);
    }
}