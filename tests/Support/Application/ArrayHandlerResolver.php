<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Application;

use Qmdb\Shared\Application\Exception\UnhandledMessageException;
use Qmdb\Shared\Application\Handler\HandlerResolver;

final readonly class ArrayHandlerResolver implements HandlerResolver
{
    /** @param array<string, object> $handlers */
    public function __construct(private array $handlers)
    {
    }

    public function resolve(string $serviceId): object
    {
        return $this->handlers[$serviceId]
            ?? throw new UnhandledMessageException(sprintf('Unknown test handler "%s".', $serviceId));
    }
}
