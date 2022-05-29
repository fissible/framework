<?php declare(strict_types=1);

namespace Fissible\Framework\Routing;

use Fissible\Framework\Collection;
use Fissible\Framework\Exceptions\Http\MethodNotAllowedError;
use Fissible\Framework\Exceptions\Http\NotFoundError;
use Fissible\Framework\Http\Request;
use Fissible\Framework\Str;
use Psr\Http\Message\UriInterface;
use React\Http\Message\ServerRequest;

class Route
{
    public static Collection $Table;

    public string $name;

    public string $url;

    private Collection $Parameters;

    private static array $globalMiddleware;

    public function __construct(
        private string $method,
        private string $uri,
        private $action,
        private ?Guard $Guard = null,
        private array $middleware = []
    )
    {}

    public static function middleware(array $globalMiddleware = [])
    {
        static::$globalMiddleware = $globalMiddleware;
    }

    /**
     * @todo: implement
     */
    public static function group(callable $group)
    {
        throw new \RuntimeException('Not implemented');
        $group();
        unset(static::$globalMiddleware);
    }

    public static function delete(string $uri, callable|array $action, ?Guard $Guard = null): Route
    {
        return static::registerRoute('DELETE', $uri, $action, $Guard);
    }

    public static function get(string $uri, callable|array $action, ?Guard $Guard = null): Route
    {
        return static::registerRoute('GET', $uri, $action, $Guard);
    }

    public static function lookup(string $method, UriInterface $uri)
    {
        $url = $uri->getPath();

        $Matches = static::$Table->filter(function (Route $Route) use ($url) {
            return $Route->matches($url);
        });

        if ($Matches->empty()) {
            throw new NotFoundError($url);
        }

        $Matches = $Matches->filter(function (Route $Route) use ($method) {
            if ($Route->getMethod() !== 'ANY' && $Route->getMethod() !== strtoupper($method)) {
                return false;
            }
            return true;
        });

        if ($Matches->empty()) {
            throw new MethodNotAllowedError($method, $url);
        }

        $Route = $Matches->sort(function (Route $RouteA, Route $RouteB) use ($url) {
            $compareA = Str::before($RouteA->getUri(), '{') ?: $RouteA->getUri();
            $compareB = Str::before($RouteB->getUri(), '{') ?: $RouteB->getUri();
            $levA = levenshtein($url, $compareA);
            $levB = levenshtein($url, $compareB);

            if ($levA === $levB) {
                return 0;
            }
            return $levB > $levA ? -1 : 1;
        })->first();

        if ($Route) {
            $Route->url = $url;
        }

        return $Route;
    }

    public static function patch(string $uri, callable|array $action, ?Guard $Guard = null): Route
    {
        return static::registerRoute('PATCH', $uri, $action, $Guard);
    }

    public static function post(string $uri, callable|array $action, ?Guard $Guard = null): Route
    {
        return static::registerRoute('POST', $uri, $action, $Guard);
    }

    public static function put(string $uri, callable|array $action, ?Guard $Guard = null): Route
    {
        return static::registerRoute('PUT', $uri, $action, $Guard);
    }

    public static function table(): Collection
    {
        return static::$Table;
    }



    public function getAction(): array|callable
    {
        return $this->action;
    }

    public function getGuard(): ?Guard
    {
        return $this->Guard;
    }

    public function getId(): string
    {
        return $this->method . ':' . $this->uri;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getName(): ?string
    {
        return $this->name ?? null;
    }

    public function getSuppliedParameters(string $requestTarget)
    {
        $parameterKeys = $this->getParameterNames();
        $parameters = $this->pregMatch($requestTarget) ?: [];

        foreach ($parameterKeys as $name) {
            if (!array_key_exists($name, $parameters)) {
                $parameters[$name] = null;
            }
        }

        return $parameters;
    }

    public function getParameter(string $name): ?RouteParameter
    {
        return $this->getParameters()->first(function (RouteParameter $Parameter) use ($name) {
            return $Parameter->getName() === $name;
        });
    }

    public function getParameters(): Collection
    {
        if (!isset($this->Parameters)) {
            $this->Parameters = $this->parseParameters();
        }

        return $this->Parameters;
    }

    public function getParameterNames(): array
    {
        return $this->getParameters()->map(function (RouteParameter $Parameter) {
            return $Parameter->getName();
        })->toArray();
    }
    
    public function getUri(): string
    {
        return $this->uri;
    }

    public function hasParams(): bool
    {
        return $this->paramCount() > 0;
    }

    public function heirarchy()
    {
        $routes = [];
        $parts = explode('/', $this->url);
        
        array_pop($parts);

        if (count($parts) === 1 && strlen($parts[0]) === 0) {
            $parent = '/';
        } else {
            $parent = implode('/', $parts);
        }

        $Request = new Request(new ServerRequest('GET', $parent));
        $uri = $Request->getUri();

        try {
            while ($Route = static::lookup('GET', $uri)) {
                $Route->url = $parent;
                $routes[$parent] = $Route;

                array_pop($parts);

                if (count($parts) === 1 && strlen($parts[0]) === 0) {
                    $parent = '/';
                } else {
                    $parent = implode('/', $parts);
                }

                $Request = new Request(new ServerRequest('GET', $parent));
                $uri = $Request->getUri();
            }
        } catch (NotFoundError $e) {
            //
        }

        return new Collection(array_reverse($routes));
    }

    public function matches(string $url): bool
    {
        if ($this->getUri() === $url && !$this->hasParams()) {
            return true;
        }

        $matches = $this->pregMatch($url);

        if ($matches === false) {
            return false;
        }

        $Parameters = $this->getParameters();
        $RequiredParameters = $Parameters->filter(function (RouteParameter $Parameter) {
            return $Parameter->isRequired();
        });

        if (empty($matches) && $RequiredParameters->count() > 0) {
            return false;
        }

        $Parameters->each(function (RouteParameter $Parameter) use ($matches, $url) {
            if ($Parameter->isRequired() && !isset($matches[$Parameter->getName()])) {
                return false;
            }
        });

        return true;
    }

    public function name(string $name): self
    {
        if (!isset($this->name) || $this->name !== $name) {
            static::table()->each(function ($Route) use ($name) {
                if ($Route->getName() === $name) {
                    throw new \InvalidArgumentException(sprintf('The Route name "%s" already exists.', $name));
                }
            });
            $this->name = $name;
        }

        return $this;
    }

    /**
     * Given a requested URL match against the config.
     * 
     * @param string $url
     * @return array|false
     */
    public function pregMatch(string $url): array|false
    {
        $values = [];
        $routeUri = $this->getUri();
        $pattern = '#^' . $routeUri;

        $this->getParameters()->each(function ($Parameter) use (&$pattern) {
            $parameterName = $Parameter->getName();
            $search = '';
            $replace = '';
            if ($Parameter->isRequired()) {
                $search = '{' . $parameterName . '}';
                $replace = '(?P<' . $parameterName . '>[a-z0-9_-]+)';
            } else {
                $search = '/{' . $parameterName . '?}';
                $replace = '(?:/(?P<' . $parameterName . '>[a-z0-9_-]+))?';
            }
            $pattern = str_replace($search, $replace, $pattern);
        });
        $pattern  .= '$#i';

        if (preg_match_all($pattern, $url, $matches, PREG_OFFSET_CAPTURE | PREG_PATTERN_ORDER)) {
            $this->getParameters()->each(function (RouteParameter $Parameter) use ($matches, &$values) {
                foreach ($matches as $key => $match) {
                    if ($key === $Parameter->getName() && strlen($match[0][0]) > 0) {
                        $values[$key] = $match[0][0];
                    }
                }
            });
        } else {
            return false;
        }

        return $values;
    }

    public function paramCount(): int
    {
        return $this->getParameters()->count();
    }

    /**
     * Get the route URL, replace any parameter placeholders with supplied values.
     * 
     * @param array $parameters
     * @return string
     */
    public function url(array $parameters = []): string
    {
        $url = $this->getUri();

        $this->getParameters()->each(function (RouteParameter $Parameter) use (&$url, $parameters) {
            $find = '{' . $Parameter->getName() . ($Parameter->isRequired() ? '' : '?') . '}';
            if (Str::contains($url, $find) && isset($parameters[$Parameter->getName()])) {
                $value = $parameters[$Parameter->getName()];
                $url = Str::replace($url, $find, $value);
            }
        });

        return $url;
    }

    /**
     * Parse the parameter placeholders in the URI.
     * 
     * @return Collection
     */
    private function parseParameters(): Collection
    {
        $offset = 0;
        $closePos = 0;
        $Parameters = new Collection();

        while ($openPos = strpos($this->uri, '{', $offset)) {
            if ($closePos = strpos($this->uri, '}', $openPos)) {
                $length = $closePos - $openPos - 1;
                $name = substr($this->uri, $openPos + 1, $length);
                $required = true;

                if (Str::endsWith($name, '?')) {
                    $required = false;
                    $name = rtrim($name, '?');
                }

                $Parameters->push(new RouteParameter($name, $required));
                $offset = $closePos;
            }
        }

        return $Parameters;
    }

    private static function registerRoute(string $method, string $uri, callable|array $action, ?Guard $Guard = null): Route
    {
        if (!isset(static::$Table)) {
            static::$Table = new Collection();
        }

        $method = strtoupper($method);
        $id = $method . ':' . $uri;
        $middleware = static::$globalMiddleware ?? [];
        $Route = new static($method, $uri, $action, $Guard, $middleware);

        static::$Table->set($id, $Route);

        return $Route;
    }
}