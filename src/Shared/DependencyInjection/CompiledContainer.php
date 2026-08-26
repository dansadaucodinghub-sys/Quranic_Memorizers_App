<?php

declare(strict_types=1);

namespace Qmdb\Shared\DependencyInjection;

use Psr\Container\ContainerInterface;
use Throwable;

final class CompiledContainer implements ContainerInterface
{
    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, true> */
    private array $resolving = [];

    /**
     * @param array<string, ServiceDefinition> $definitions
     * @param array<string, string> $aliases
     */
    public function __construct(
        private readonly array $definitions,
        private readonly array $aliases,
    ) {
    }

    public function has(string $id): bool
    {
        $target = $this->aliases[$id] ?? $id;

        return isset($this->definitions[$target]);
    }

    public function get(string $id): mixed
    {
        return $this->resolve($id);
    }

    private function resolve(string $id): object
    {
        $target = $this->aliases[$id] ?? $id;
        if (!isset($this->definitions[$target])) {
            throw new ServiceNotFoundException(sprintf('Service "%s" is not defined.', $id));
        }

        if (isset($this->instances[$target])) {
            return $this->instances[$target];
        }

        if (isset($this->resolving[$target])) {
            throw new ContainerCyclicDependencyException(sprintf(
                'Circular service resolution detected for "%s".',
                $target,
            ));
        }

        $definition = $this->definitions[$target];
        if ($definition->instance !== null) {
            return $this->instances[$target] = $definition->instance;
        }

        $this->resolving[$target] = true;
        try {
            $factory = $definition->factory;
            if ($factory === null) {
                throw new InvalidServiceException(sprintf('Service "%s" has no factory.', $target));
            }

            $resolver = new RestrictedDependencyResolver(
                $definition->dependencies,
                fn (string $dependencyId): object => $this->resolve($dependencyId),
                $target,
            );

            try {
                $service = $factory->create($resolver);
            } catch (DependencyInjectionException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw new DependencyInjectionException(
                    sprintf('Service "%s" factory execution failed.', $target),
                    0,
                    $exception,
                );
            }

            if ((class_exists($target) || interface_exists($target)) && !$service instanceof $target) {
                throw new InvalidServiceException(sprintf(
                    'Service "%s" factory returned an incompatible object.',
                    $target,
                ));
            }

            return $this->instances[$target] = $service;
        } finally {
            unset($this->resolving[$target]);
        }
    }
}
