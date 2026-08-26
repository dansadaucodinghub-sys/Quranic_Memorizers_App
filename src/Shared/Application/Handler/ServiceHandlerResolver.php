<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Handler;

use Closure;
use Qmdb\Shared\Application\Exception\UnhandledMessageException;

final readonly class ServiceHandlerResolver implements HandlerResolver
{
    /**
     * @param list<string> $allowedServiceIds
     * @param Closure(string): object $resolver
     */
    public function __construct(
        private array $allowedServiceIds,
        private Closure $resolver,
    ) {
    }

    public function resolve(string $serviceId): object
    {
        if (!in_array($serviceId, $this->allowedServiceIds, true)) {
            throw new UnhandledMessageException(sprintf(
                'Handler service "%s" is not present in the compiled handler maps.',
                $serviceId,
            ));
        }

        return ($this->resolver)($serviceId);
    }
}
