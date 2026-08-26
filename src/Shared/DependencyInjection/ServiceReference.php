<?php

declare(strict_types=1);

namespace Qmdb\Shared\DependencyInjection;

final readonly class ServiceReference
{
    /**
     * @template T of object
     * @param class-string<T> $serviceId
     * @return T
     */
    public static function get(DependencyResolver $resolver, string $serviceId): object
    {
        $service = $resolver->get($serviceId);
        if (!$service instanceof $serviceId) {
            throw new InvalidServiceException(sprintf(
                'Resolved service "%s" has an incompatible type.',
                $serviceId,
            ));
        }

        return $service;
    }
}
