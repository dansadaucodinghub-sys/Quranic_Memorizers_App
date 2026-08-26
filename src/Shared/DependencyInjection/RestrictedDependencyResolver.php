<?php

declare(strict_types=1);

namespace Qmdb\Shared\DependencyInjection;

use Closure;

final readonly class RestrictedDependencyResolver implements DependencyResolver
{
    /**
     * @param list<string> $allowedServiceIds
     * @param Closure(string): object $resolver
     */
    public function __construct(
        private array $allowedServiceIds,
        private Closure $resolver,
        private string $consumerServiceId,
    ) {
    }

    public function get(string $serviceId): object
    {
        if (!in_array($serviceId, $this->allowedServiceIds, true)) {
            throw new UndeclaredDependencyException(sprintf(
                'Service "%s" attempted to resolve undeclared dependency "%s".',
                $this->consumerServiceId,
                $serviceId,
            ));
        }

        return ($this->resolver)($serviceId);
    }
}
