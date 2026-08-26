<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Routing;

final readonly class AutomaticOptions implements RouteMatchResult
{
    /** @var list<HttpMethod> */
    private array $allowedMethods;

    /** @param list<HttpMethod> $allowedMethods */
    public function __construct(array $allowedMethods)
    {
        $this->allowedMethods = HttpMethod::sort($allowedMethods);
    }

    /** @return list<HttpMethod> */
    public function allowedMethods(): array
    {
        return $this->allowedMethods;
    }

    public function allowHeader(): string
    {
        return implode(', ', array_map(
            static fn (HttpMethod $method): string => $method->value,
            $this->allowedMethods,
        ));
    }
}
