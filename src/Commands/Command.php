<?php declare(strict_types=1);

namespace Fissible\Framework\Commands;

use Clue\React\Stdio\Stdio;
use Fissible\Framework\Application;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;

abstract class Command
{
    /**
     * The string used to invoke the command
     */
    public static string $command = '';

    /**
     * The description of the command
     */
    public static string $description = '';

    /**
     * Command arguments configuration
     */
    public static $arguments = [/*

        EXAMPLES

        Optional argument
        'file' => []

        Required argument
        'path' => [
            'required' => true
        ]
    */];

    /**
     * Command options configuration
     */
    public static $options = [/*

        EXAMPLES

        Boolean flag
        'p|print' => [
            'description' => 'Print the email that is sent.'
        ],

        Option with unrestricted argument input; if option not provided it is initialized with a default
        'd|delay' => [
            'argument' => 'minutes',
            'default' => '0',
            'description' => 'Minutes to delay sending.'
        ],

        Option with argument input restricted to the configured values; if option not provided it is initialized with a default
        'f|format' => [
            'argument' => 'format',
            'default' => 'html',
            'description' => 'Email format.',
            'values' => ['html', 'text']
        ],

        Option with argument input restricted to the configured values; if option not provided it is initialized to null
        'verbose' => [
            'argument' => 'verbosity',
            'description' => 'The verbosity level.',
            'values' => [1, 2, 3]
        ]
        */];

    protected Application $app;

    protected Arguments $args;

    private Stdio $stdio;

    public function __construct(?Application $app = null)
    {
        if ($app) {
            $this->app = $app;
        }

        $this->args = new Arguments([
            'options' => static::$options,
            'arguments' => static::$arguments
        ]);
    }

    public function init(array $arguments)
    {
        $this->setArguments($arguments);
    }

    abstract public function run(): PromiseInterface;

    /**
     * Return the description and usage of the command.
     * 
     * @return string
     */
    public function help(): string
    {
        return static::$description . PHP_EOL . PHP_EOL . $this->usage() . PHP_EOL;
    }

    /**
     * Return the command string and description.
     * 
     * @return string
     */
    public static function summary(string $alias = null): string
    {
        $out = '   ' . $alias ?? static::$command;

        if (isset(static::$description)) {
            $out .= ' - ' . static::$description;
        }

        $out .= PHP_EOL;

        return $out;
    }

    protected function requireDatabase()
    {
        if (!$this->app->db()) {
            throw new \Exception('Database not configured.');
        }
    }

    /**
     * Return the usage of the command
     * 
     * @return string
     */
    protected function usage(): string
    {
        $args = $this->args->usage();

        $out = sprintf("Usage: %s %s%s %s", SCRIPT_NAME, static::$command ?? '<command>', $args['options'], implode(' ', $args['arguments']));

        if (count($args['expansions']) > 0) {
            $out .= PHP_EOL;

            foreach ($args['expansions'] as $left => $right) {
                $out .= sprintf('   %s %s', $left, $right) . PHP_EOL;
            }
        }

        return $out;
    }

    protected function setArguments(array $arguments): self
    {
        $this->args->setRaw($arguments);

        return $this;
    }

    public function argument(string $name): mixed
    {
        return $this->args->argument($name);
    }

    public function arguments(): mixed
    {
        return $this->args->arguments();
    }

    public function option(string $name): mixed
    {
        return $this->args->option($name);
    }

    public function options(): mixed
    {
        return $this->args->options();
    }

    /**
     * Get a input/output instance.
     */
    protected function stdio()
    {
        if (!isset($this->stdio)) {
            $this->stdio = new Stdio(Loop::get());
        }
        return $this->stdio;
    }
    
    /**
     * Invoke the run method.
     */
    public function __invoke()
    {
        $args = func_get_args();

        return $this->run(...$args);
    }

    public function __get(string $name): ?string
    {
        switch ($name) {
            case 'command':
                return static::$command ?? get_class($this);
            case 'description':
                return static::$description;
        }

        return null;
    }

    public function __isset(string $name): bool
    {
        return in_array($name, ['command', 'description']);
    }
}