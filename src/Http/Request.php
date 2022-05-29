<?php declare(strict_types=1);

namespace Fissible\Framework\Http;

use Fissible\Framework\Application;
use Fissible\Framework\Repositories\UserRepository;
use Fissible\Framework\Services\AuthService;
use Fissible\Framework\Session;
use Fissible\Framework\Str;
use Fissible\Framework\Traits\MagicProxy;
use Fissible\Framework\Validation\Validator;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use React\Promise;
use WyriHaximus\React\Http\Middleware\SessionMiddleware;

class Request extends \RingCentral\Psr7\MessageTrait implements ServerRequestInterface
{
    use MagicProxy;

    private array $GET;

    private array $POST;

    private $attributes = array();

    private $serverParams;

    private $fileParams = array();

    private $cookies = array();

    private $queryParams = array();

    private $parsedBody;

    private ServerRequestInterface $request;

    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $this->proxied = $request;
        static::$proxiedClass = get_debug_type($request);
    }

    public static function redirect(string $location, int $statusCode = 302)
    {
        return Response::redirect($location, $statusCode);
    }

    public static function remoteAddress(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    public function contentType(): string
    {
        return $this->request->getHeader('Content-Type')[0] ?? '';
    }

    public function expectsJson(): bool
    {
        return Str::contains($this->contentType(), 'json');
    }

    public function files()
    {
        return $this->request->getUploadedFiles();
    }

    public function get(?string $key = null, mixed $default = null): mixed
    {
        if (!isset($this->GET)) {
            $this->GET = $this->request->getQueryParams();
        }

        if ($key) {
            return $this->GET[$key] ?? $default;
        }

        return $this->GET;
    }

    public function httpRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        $input = array_merge($this->get(), $this->post());

        if ($key) {
            return $input[$key] ?? $default;
        }

        return $input;
    }

    public function is(string $uriPath): bool
    {
        $uriPath = '/' . ltrim($uriPath, '/');

        return $this->getUri()->getPath() === strtolower($uriPath);
    }

    public function isFile()
    {
        $rootPath = Application::singleton()->config()->get('static-files.path') ?? 'public';
        $filePath = $this->getUri()->getPath();

        if (!Str::startsWith($rootPath, DIRECTORY_SEPARATOR)) {
            $rootPath = ROOT_PATH . DIRECTORY_SEPARATOR . $rootPath;
        }

        $file = $rootPath . $filePath;

        return file_exists($file);
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if (!isset($this->POST)) {
            $this->POST = [];

            if (in_array($this->contentType(), ['application/x-www-form-urlencoded', 'multipart/form-data'])) {
                $this->POST = $this->request->getParsedBody();
            } else {
                $this->POST = json_decode((string) $this->request->getBody(), true) ?? [];
            }
        }

        if ($key) {
            return $this->POST[$key] ?? $default;
        }

        return $this->POST;
    }

    public function pushSession(string $key, array ...$values): self
    {
        if ($this->session()) {
            $contents = $this->session()->getContents();
            if (!isset($contents[$key])) {
                $contents[$key] = [];
            }

            foreach ($values as $value) {
                $contents[$key][] = $value;
            }

            $this->session()->setContents($contents);
        }

        return $this;
    }

    public function putSession(string $key, mixed $value = null): self
    {
        if ($this->session()) {
            if ($contents = $this->session()->getContents()) {
                if ($value === null) {
                    unset($contents[$key]);
                    $this->session()->setContents($contents);
                } else {
                    $this->session()->setContents(array_merge($contents, [
                        $key => $value
                    ]));
                }
            }
        }

        return $this;
    }

    public function setCurrentLocation()
    {
        // Set the current/previous URLs
        if (!$this->isFile() && $this->Session()->isActive()) {
            $current = $this->getRequestTarget();
            if ($current !== $this->Session()->get('current')) {
                $this->Session()->set('previous', $this->Session()->get('current'));
            }
            $this->Session()->set('current', $current);
        }
    }

    public function flashPrevious(array $exclude = [])
    {
        $alwaysForget = ['_csrf', 'password', 'password_confirm', 'ssn'];

        if ($errors = $this->Session()->flashed('errors')) {
            $exclude = array_merge($exclude, $errors);
        }

        $input = array_diff_key($this->input(), $exclude);

        foreach ($alwaysForget as $key) {
            if (isset($input[$key])) unset($input[$key]);
        }

        $this->Session()->flash('input', $input);

        return $this;
    }

    public function flushPrevious()
    {
        $this->Session()->flash('input', null);

        return $this;
    }

    public function hasErrors(): bool
    {
        return !empty($this->Session()->flashed('errors'));
    }

    public function hasInput()
    {
        return !empty($this->input());
    }

    public function redirectBack($statusCode = 302)
    {
        $location = '/';
        if ($previous = $this->Session()->get('current')) {
            $location = $previous;
        }

        if ($statusCode > 399) {
            $statusCode = 302;
        }

        return Response::redirect($location, $statusCode);
    }

    public function redirectBackWithErrors(array $errors, $statusCode = 302)
    {
        $this->Session()->flash('errors', $errors);

        return $this->redirectBack($statusCode);
    }

    public function redirectToIntended(string $default = '/')
    {
        if ($intended = $this->Session()->pull('intended')) {
            return $this->redirect($intended);
        }
        return $this->redirect($default);
    }

    public function Session(): Session
    {
        return new Session($this->request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME));
    }

    public function authenticatedApiUser(): Promise\PromiseInterface
    {
        if ($claims = AuthService::getClaims($this->httpRequest())) {
            return UserRepository::getById($claims->user_id)->then(function ($User) {
                return Promise\resolve($User);
            }, function () {
                return Promise\reject(new \Exception('User not logged in.'));
            });
        }

        return Promise\reject(new \Exception('User not logged in.'));
    }

    public function authenticatedSessionUser(): Promise\PromiseInterface
    {
        if ($session = $this->session()) {
            $contents = $session->getContents();

            if (isset($contents['User'])) {
                return Promise\resolve($contents['User']);
            } elseif (isset($contents['user_id'])) {
                return UserRepository::getById($contents['user_id']);
            }
        }
        return Promise\reject(new \Exception('User not logged in.'));
    }

    public function uri(): string
    {
        return $this->getUri()->getPath();
    }

    public function validate(array $rules, array $messages = []): Promise\PromiseInterface
    {
        $input = $this->input();

        $Validator = new Validator($rules, $messages);

        return $Validator->validate($input);
    }


    public function getServerParams()
    {
        return $this->request->getServerParams();
    }

    public function getCookieParams()
    {
        return $this->request->getCookieParams();
    }

    public function withCookieParams(array $cookies)
    {
        $new = clone $this;
        $new->cookies = $cookies;
        return $new;
    }

    public function getQueryParams()
    {
        return $this->request->getQueryParams();
    }

    public function withQueryParams(array $query)
    {
        return $this->request->withQueryParams($query);
    }

    public function getUploadedFiles()
    {
        return $this->request->getUploadedFiles();
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        $new = clone $this;
        $new->fileParams = $uploadedFiles;
        return $new;
    }

    public function getParsedBody()
    {
        return $this->request->getParsedBody();
    }

    public function withParsedBody($data)
    {
        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    public function getAttributes()
    {
        return $this->request->getAttributes();
    }

    public function getAttribute($name, $default = null)
    {
        return $this->request->getAttribute($name, $default);
    }

    public function withAttribute($name, $value)
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    public function withoutAttribute($name)
    {
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }

    public function getRequestTarget()
    {
        return $this->request->getRequestTarget();
    }

    public function withRequestTarget($requestTarget)
    {
        return $this->request->withRequestTarget($requestTarget);
    }

    public function getMethod()
    {
        return $this->request->getMethod();
    }

    public function withMethod($method)
    {
        return $this->request->withMethod($method);
    }

    public function getUri()
    {
        return $this->request->getUri();
    }

    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        return $this->request->withUri($uri, $preserveHost);
    }

    public function withHeader($header, $value)
    {
        $newInstance = new static($this->request->withHeader($header, $value));
        return $newInstance;
    }


    public static function __callStatic($name, $args = [])
    {
        if ($name === 'ip') {
            return static::remoteAddress();
        }
    }

    public function __call($name, $args = [])
    {
        if ($name === 'ip') {
            return static::remoteAddress();
        } elseif ($name === 'user') {
            if ($this->expectsJson()) {
                return $this->authenticatedApiUser();
            } else {
                return $this->authenticatedSessionUser();
            }
        }

        return call_user_func_array([$this->request, $name], $args);
    }

    public function __get(string $name)
    {
        if ($name === 'ip') {
            return static::remoteAddress();
        }
    }
}