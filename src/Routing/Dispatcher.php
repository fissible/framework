<?php declare(strict_types=1);

namespace Fissible\Framework\Routing;

use Fissible\Framework\Http\Controllers\Controller;
use Fissible\Framework\Http\Request;
use Fissible\Framework\Http\Response;
use Fissible\Framework\Traits\RequiresServiceContainer;
use Illuminate\Contracts\View\View;
use React\Http\Message\Response as ReactResponse;
use React\Promise;

class Dispatcher
{
    use RequiresServiceContainer;

    /**
     * Dispatch the request
     */
    public function __invoke(Request $Request, Route $Route): Promise\PromiseInterface
    {
        [$callable, $ReflectionFunction] = $this->getCallableAndReflection($Route->getAction());

        $parameters = $Route->getSuppliedParameters($Request->getRequestTarget());
        array_unshift($parameters, $Request);
        $parameters = $this->getFinalParameters($ReflectionFunction, $parameters);

        $response = $callable(...$parameters);

        return $this->getResponse($response);
    }

    /**
     * Convert the response data into a ReactResponse.
     */
    public function getResponse(mixed $response, bool $interior = false): Promise\PromiseInterface
    {
        if ($response === null) {
            $response = Response::make('Not found', 404, ['Content-Type' => 'application/json']);
        }

        if ($response !== null) {
            if (is_string($response)) {
                $response = Response::make($response, 200, ['Content-Type' => 'text/html']);
            }

            if ($response instanceof View) {
                $response = Response::make($response->render(), 200, ['Content-Type' => 'text/html']);
            }

            if ($response instanceof Promise\PromiseInterface && !$interior) {
                return $response->then(function ($response) {
                    return $this->getResponse($response, true);
                });
            }

            if (!($response instanceof Promise\PromiseInterface) && !($response instanceof ReactResponse)) {
                $response = Response::make($response, 200, ['Content-Type' => 'application/json']);
            }
        }

        if (!($response instanceof Promise\PromiseInterface)) {
            $response = Promise\resolve($response);
        }

        return $response;
    }

    public function getCallableAndReflection(Controller|\Closure|array $callable)
    {
        if ($callable instanceof Controller) {
            $Reflection = new \ReflectionMethod($callable, '__invoke');
            $callable = [$callable, '__invoke'];
        } elseif ($callable instanceof \Closure) {
            $Reflection = new \ReflectionFunction($callable);
        } else {
            if (!is_array($callable)) {
                $callable = [$callable, '__invoke'];
            } elseif (count($callable) === 1) {
                $callable[] = '__invoke';
            }

            if (is_string($callable[0])) {
                $callable[0] = new $callable[0]();
            }

            $Reflection = new \ReflectionMethod($callable[0], $callable[1]);
        }

        return [
            $callable,
            $Reflection
        ];
    }

    /**
     * If the route action requires more parameters than were provided, attempt to supply missing parameters.
     */
    public function getFinalParameters(\ReflectionFunctionAbstract $ReflectionFunction, array $suppliedParameters): array
    {
        if (count($suppliedParameters) < $ReflectionFunction->getNumberOfRequiredParameters()) {
            $ReflectionParameters = $ReflectionFunction->getParameters();

            $finalParameters = array_fill(0, $ReflectionFunction->getNumberOfParameters(), null);
            foreach ($ReflectionParameters as $index => $Param) {
                if ($Param->isOptional() && is_null($finalParameters[$index])) {
                    $finalParameters[$index] = $Param->getDefaultValue();
                }
            }

            return $this->supplementParameters($ReflectionParameters, $finalParameters, $suppliedParameters);
        }

        return $suppliedParameters;
    }

    public function supplementParameters(array $ReflectionParameters, array $parameters, array $supplied)
    {
        $Container = $this->Container();

        // Replace placeholder arguments with any matching types in the service container
        foreach ($ReflectionParameters as $index => $Param) {
            $ParamTypes = $this->getAllTypes($Param);

            foreach ($ParamTypes as $Type) {
                if (!$Type->isBuiltin()) {
                    if ($Container->has($Type->getName())) {
                        $parameters[$index] = $Container->instance($Type->getName());
                    } elseif ($Container->provides($Type->getName())) {
                        $parameters[$index] = $Container->make($Type->getName());
                    }
                }
            }
        }

        // Replace placeholder arguemnts with supplied arguments with the matching type
        foreach ($ReflectionParameters as $index => $Param) {
            $ParamTypes = $this->getAllTypes($Param);

            foreach ($ParamTypes as $Type) {
                $paramTypeName = $Type->getName();

                foreach ($supplied as $sIndex => $param) {
                    if (get_debug_type($param) === $paramTypeName) {
                        $parameters[$index] = $param;
                        unset($supplied[$sIndex]);
                        continue(3);
                    }
                }
            }
        }

        // Fill in any remaining placeholders with any remaining supplied arguments
        $param = array_shift($supplied);
        foreach ($parameters as $index => $parameter) {
            if ($param === null) break;

            if ($parameter === null) {
                $parameters[$index] = $param;
                $param = array_shift($supplied);
            }
        }

        return $parameters;
    }

    private function getAllTypes(\ReflectionParameter $reflectionParameter): array
    {
        $reflectionType = $reflectionParameter->getType();

        if (!$reflectionType) return [];

        return $reflectionType instanceof \ReflectionUnionType
            ? $reflectionType->getTypes()
            : [$reflectionType];
    }
}