<?php declare(strict_types=1);

namespace Fissible\Framework\Facades;

use Fissible\Framework\Traits\RequiresServiceContainer;
use Illuminate\Contracts;
use Jenssegers\Blade\Blade;

class View
{
    use RequiresServiceContainer;

    private array $config;

    private string $view;

    private Contracts\Support\Arrayable|array $data;

    private array $mergeData;

    public function __construct(array $config = [])
    {
        if (!isset($config['views_path'])) {
            $config['views_path'] = $this->getConfigValue('views.path');
        }

        if (!isset($config['cache_path'])) {
            $config['cache_path'] = $this->getConfigValue('cache.path');
        }

        $this->config = $config;
    }

    public function Blade(): Blade
    {
        $Blade = new Blade($this->config['views_path'], $this->config['cache_path']);

        foreach (static::directives() as $name => $callable) {
            $Blade->directive($name, $callable);
        }

        return $Blade;
    }

    public static function make(string $view, Contracts\Support\Arrayable|array $data = [], array $mergeData = [], array $config = []): Contracts\View\View
    {
        $View = new View($config);
        return $View->Blade()->make($view, $data, $mergeData);
    }

    public static function render(string $view, Contracts\Support\Arrayable|array $data = [], array $mergeData = [], array $config = []): string
    {
        $View = new View($config);
        return $View->Blade()->render($view, $data, $mergeData);
    }

    protected static function directives(): array
    {
        return [
            'csrf' => function () {
                return "<?php echo '<input type=\"hidden\" name=\"_csrf\" value=\"' . Session()->token() . '\" />' ?>";
            },
            'prev' => function ($arguments) {
                $arguments = explode(' ', $arguments);
                $name = $arguments[0];
                $default = $arguments[1] ?? '';
                return "<?php echo Session()->prev({$name}, {$default}) ?>";
            }
        ];
    }

    protected function getConfigValue(string $key): mixed
    {
        return self::app()->config()->get($key);
    }
}