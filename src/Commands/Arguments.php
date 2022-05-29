<?php declare(strict_types=1);

namespace Fissible\Framework\Commands;

use Clue\React\Stdio\Stdio;
use Fissible\Framework\Application;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;

class Arguments
{
    public $arguments = [];

    public $config = [
        'options' => [],
        'arguments' => []
    ];

    public $options = [];

    protected bool $parsed = false;

    protected array $raw;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function setRaw(array $arguments)
    {
        $this->raw = $arguments;
    }

    public function argument(string $name): mixed
    {
        return $this->arguments()[$name] ?? null;
    }

    public function arguments(): mixed
    {
        if (!$this->parsed) {
            $this->parse();
        }

        return $this->arguments;
    }

    public function option(string $name): mixed
    {
        return $this->options()[$name] ?? null;
    }

    public function options(): mixed
    {
        if (!$this->parsed) {
            $this->parse();
        }

        return $this->options;
    }

    /**
     * Parse the raw parameters provided to the command into options/flags and arguments.
     */
    public function parse()
    {
        $arguments = $this->raw;

        // Initialize the options
        foreach ($this->config['options'] as $flag => $option) {
            if (false === strpos($flag, '|')) {
                $long = strlen($flag) > 0 ? $flag : '';
                $short = strlen($flag) > 0 ? '' : $flag;
            } else {
                $flagParts = explode('|', $flag);
                $long = strlen($flagParts[0]) > strlen($flagParts[1]) ? $flagParts[0] : $flagParts[1];
                $short = strlen($flagParts[0]) > strlen($flagParts[1]) ? $flagParts[1] : $flagParts[0];
            }

            $name = $long ?? $short;
            $alias = $short ?? null;

            $this->options[$name] = $option['default'] ?? null;
            if (is_null($this->options[$name]) && !isset($option['argument'])) {
                $this->options[$name] = false;
            }
            if (!is_null($alias) && strlen($alias) > 0 && $alias !== $name) {
                $this->options[$alias] = $this->options[$name];
            }

            foreach ($arguments as $key => $arg) {
                $nextKey = $key + 1;
                $nextArg = $arguments[$nextKey] ?? null;
                $unsets = [];

                // If the argument starts with '--' or '-'
                if ((substr($arg, 0, 2) === '--' && ltrim($arg, '-') === $long) || (substr($arg, 0, 1) === '-' && ltrim($arg, '-') === $short)) {
                    $this->options[$name] = $option['default'] ?? null;
                    $unsets[] = $key;

                    if (isset($option['argument']) && !is_null($nextArg) && substr($nextArg, 0, 1) !== '-') {
                        if (isset($option['values'])) {
                            $matches = array_filter($option['values'], function ($val) use ($nextArg) {
                                if (is_string($val) && is_string($nextArg)) {
                                    return strtolower($val) == strtolower($nextArg);
                                }
                                return $val == $nextArg;
                            });

                            if (count($matches) === 0) {
                                throw new \InvalidArgumentException(sprintf(
                                    '%s option flag value must be in "%s"; "%s" provided',
                                    $name,
                                    implode(
                                        '", "',
                                        $option['values']
                                    ),
                                    $nextArg
                                ));
                            }
                            $this->options[$name] = reset($matches) ?? $option['default'] ?? null;
                        } else {
                            $this->options[$name] = $nextArg;
                        }
                        $unsets[] = $nextKey;
                    } else {
                        $this->options[$name] = true;
                    }

                    if (!is_null($alias) && strlen($alias) > 0 && $alias !== $name) {
                        $this->options[$alias] = $this->options[$name];
                    }

                    foreach ($unsets as $unset) {
                        unset($arguments[$unset]);
                    }
                }
            }
        }

        // Initialize the arguments
        foreach ($this->config['arguments'] as $flag => $argument) {
            $this->arguments[$flag] = array_shift($arguments);
        }

        $this->parsed = true;
    }

    public function usage(): array
    {
        $argumentsFlag = [];
        $optionsFlag = '';
        $expansions = [];

        // Uses [options] to indicate where the options go
        if (count($this->config['options']) > 0) {
            $optionsFlag = ' [options]';

            foreach ($this->config['options'] as $flag => $option) {
                $left = (strlen($flag) === 1 ? '-' : '--') . $flag;

                if (strpos($flag, '|') !== false) {
                    $shortLong = explode('|', $flag);

                    if (isset($option['default'])) {
                        $left = sprintf('-%s <%s>', $shortLong[0], $option['argument'] ?? $shortLong[1]);
                    } else {
                        $left = '-' . $shortLong[0];
                    }

                    if (isset($option['values'])) {
                        $left .= ' [' .  implode('|', $option['values']) . ']';
                    }
                } elseif (isset($option['default'])) {
                    $left = sprintf('%s <%s>', $left, $option['argument'] ?? 'arg');
                }

                $expansions[$left] = '';

                if (isset($option['description'])) {
                    $expansions[$left] = $option['description'];
                }

                // non-boolean option: show the default
                if (isset($option['default']) && is_scalar($option['default'])) {
                    if (strlen($expansions[$left]) > 0) {
                        $expansions[$left] .= ' ';
                    }
                    $expansions[$left] .= 'Default: ' . $option['default'];
                }
            }


            if (count($expansions) > 0) {
                // get the longest left column string
                $longest = 0;
                foreach ($expansions as $left => $_) {
                    $length = strlen($left);
                    if ($length > $longest) {
                        $longest = $length;
                    }
                }

                // pad the left column
                foreach ($expansions as $left => $right) {
                    $key = str_pad($left, $longest, ' ');
                    $expansions[$key] = $right;
                    unset($expansions[$left]);
                }

                // sort by -f and --flag (single first, then multi-length flags)
                uksort($expansions, function ($ka, $kb) {
                    $ca = substr($ka, 0, 2) === '--' ? 2 : 1;
                    $cb = substr($kb, 0, 2) === '--' ? 2 : 1;
                    if ($ca == $cb) {
                        return 0;
                    }
                    return ($ca < $cb) ? -1 : 1;
                });
            }
        }

        if (count($this->config['arguments']) > 0) {
            $requiredArguments = array_filter($this->config['arguments'], function ($ele) {
                return isset($ele['required']) && $ele['required'] === true;
            });
            $optionalArguments = array_filter($this->config['arguments'], function ($ele) {
                return !isset($ele['required']) || $ele['required'] !== true;
            });

            if (count($requiredArguments) > 0) {
                $argumentsFlag[] = implode(' ', array_keys($requiredArguments));
            }

            if (count($optionalArguments) > 0) {
                $argumentsFlag[] = implode(' ', array_map(function ($arg) {
                    return '[' . $arg . ']';
                }, array_keys($optionalArguments)));
            }
        }

        return [
            'options' => $optionsFlag,
            'arguments' => $argumentsFlag,
            'expansions' => $expansions
        ];
    }
}