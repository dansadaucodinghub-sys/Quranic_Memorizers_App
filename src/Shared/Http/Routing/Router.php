<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Routing;

final readonly class Router
{
    public function __construct(private RouteCollection $routes)
    {
    }

    public function match(?HttpMethod $method, string $validatedPath): RouteMatchResult
    {
        $pathMatches = $this->pathMatches($validatedPath);
        if ($pathMatches === []) {
            return new RouteNotFound();
        }

        $allowedMethods = $this->allowedMethods($pathMatches);
        if ($method === HttpMethod::OPTIONS) {
            $explicitOptions = $this->firstAllowed($pathMatches, HttpMethod::OPTIONS);
            if ($explicitOptions !== null) {
                return $this->matched($explicitOptions, HttpMethod::OPTIONS, false);
            }

            return new AutomaticOptions($allowedMethods);
        }

        if ($method === HttpMethod::HEAD) {
            $explicitHead = $this->firstAllowed($pathMatches, HttpMethod::HEAD);
            if ($explicitHead !== null) {
                return $this->matched($explicitHead, HttpMethod::HEAD, false);
            }

            $getFallback = $this->firstAllowed($pathMatches, HttpMethod::GET);
            if ($getFallback !== null) {
                return $this->matched($getFallback, HttpMethod::GET, true);
            }
        }

        if ($method !== null) {
            $matched = $this->firstAllowed($pathMatches, $method);
            if ($matched !== null) {
                return $this->matched($matched, $method, false);
            }
        }

        return new MethodNotAllowed($allowedMethods);
    }

    /**
     * @return list<array{route: Route, parameters: list<RouteParameter>, position: int}>
     */
    private function pathMatches(string $path): array
    {
        $matches = [];
        $position = 0;
        foreach ($this->routes as $route) {
            $parameters = $route->pattern()->match($path);
            if ($parameters !== null) {
                $matches[] = ['route' => $route, 'parameters' => $parameters, 'position' => $position];
            }
            ++$position;
        }

        usort(
            $matches,
            static function (array $left, array $right): int {
                $leftPattern = $left['route']->pattern();
                $rightPattern = $right['route']->pattern();

                return $rightPattern->staticSegmentCount() <=> $leftPattern->staticSegmentCount()
                    ?: $rightPattern->staticCharacterCount() <=> $leftPattern->staticCharacterCount()
                    ?: $left['position'] <=> $right['position'];
            },
        );

        return $matches;
    }

    /**
     * @param list<array{route: Route, parameters: list<RouteParameter>, position: int}> $matches
     * @return list<HttpMethod>
     */
    private function allowedMethods(array $matches): array
    {
        $methods = [];
        foreach ($matches as $match) {
            foreach ($match['route']->methods() as $method) {
                $methods[] = $method;
                if ($method === HttpMethod::GET) {
                    $methods[] = HttpMethod::HEAD;
                }
            }
        }
        $methods[] = HttpMethod::OPTIONS;

        return HttpMethod::sort($methods);
    }

    /**
     * @param list<array{route: Route, parameters: list<RouteParameter>, position: int}> $matches
     * @return array{route: Route, parameters: list<RouteParameter>, position: int}|null
     */
    private function firstAllowed(array $matches, HttpMethod $method): ?array
    {
        foreach ($matches as $match) {
            if ($match['route']->allows($method)) {
                return $match;
            }
        }

        return null;
    }

    /**
     * @param array{route: Route, parameters: list<RouteParameter>, position: int} $match
     */
    private function matched(array $match, HttpMethod $effectiveMethod, bool $headFallback): MatchedRoute
    {
        return new MatchedRoute(
            route: $match['route'],
            parameters: $match['parameters'],
            effectiveMethod: $effectiveMethod,
            headFallback: $headFallback,
        );
    }
}
