<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Routing;

use Qmdb\Shared\Http\Contract\Controller;

final readonly class Route
{
    /** @var list<HttpMethod> */
    private array $methods;

    /** @param list<HttpMethod> $methods */
    public function __construct(
        private string $name,
        array $methods,
        private RoutePattern $pattern,
        private Controller $controller,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/D', $name) !== 1) {
            throw new RouteCollectionException('Route name is invalid.');
        }

        if ($methods === []) {
            throw new RouteCollectionException('Route must allow at least one HTTP method.');
        }

        $sortedMethods = HttpMethod::sort($methods);
        if (count($sortedMethods) !== count($methods)) {
            throw new RouteCollectionException('Route methods must be unique.');
        }

        $this->methods = $sortedMethods;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return list<HttpMethod> */
    public function methods(): array
    {
        return $this->methods;
    }

    public function allows(HttpMethod $method): bool
    {
        return in_array($method, $this->methods, true);
    }

    public function pattern(): RoutePattern
    {
        return $this->pattern;
    }

    public function controller(): Controller
    {
        return $this->controller;
    }
}
