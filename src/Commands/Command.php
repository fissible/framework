<?php declare(strict_types=1);

namespace Fissible\Framework\Commands;

use Clue\React\Stdio\Stdio;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;

abstract class Command
{
    private Stdio $stdio;

    abstract public function run(): PromiseInterface;

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
}