<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Routing;

final readonly class RouteParameter
{
    public function __construct(private string $name, private string $value)
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name) !== 1) {
            throw new RouteCollectionException('Route parameter name is invalid.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value(): string
    {
        return $this->value;
    }
}
