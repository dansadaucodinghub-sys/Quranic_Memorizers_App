<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Routing;

final readonly class MatchedRoute implements RouteMatchResult
{
    /** @param list<RouteParameter> $parameters */
    public function __construct(
        private Route $route,
        private array $parameters,
        private HttpMethod $effectiveMethod,
        private bool $headFallback,
    ) {
    }

    public function route(): Route
    {
        return $this->route;
    }

    /** @return array<string, string> */
    public function parameters(): array
    {
        $parameters = [];
        foreach ($this->parameters as $parameter) {
            $parameters[$parameter->name()] = $parameter->value();
        }

        return $parameters;
    }

    public function effectiveMethod(): HttpMethod
    {
        return $this->effectiveMethod;
    }

    public function isHeadFallback(): bool
    {
        return $this->headFallback;
    }
}
