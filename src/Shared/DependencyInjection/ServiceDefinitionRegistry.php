<?php

declare(strict_types=1);

namespace Qmdb\Shared\DependencyInjection;

final class ServiceDefinitionRegistry
{
    /** @var array<string, ServiceDefinition> */
    private array $definitions = [];

    /** @var array<string, ServiceAlias> */
    private array $aliases = [];

    private bool $frozen = false;

    public function register(ServiceDefinition $definition): void
    {
        $this->assertOpen();
        self::assertIdentifier($definition->id, 'service');
        self::assertModuleIdentifier($definition->ownerModuleId);

        if (isset($this->definitions[$definition->id]) || isset($this->aliases[$definition->id])) {
            throw new DependencyInjectionException(sprintf(
                'Service identifier "%s" is already registered.',
                $definition->id,
            ));
        }

        $seen = [];
        foreach (array_merge($definition->dependencies, $definition->extensionDependencies) as $dependency) {
            self::assertIdentifier($dependency, 'dependency');
            if ($dependency === $definition->id) {
                throw new DependencyInjectionException(sprintf(
                    'Service "%s" cannot depend on itself.',
                    $definition->id,
                ));
            }
            if (isset($seen[$dependency])) {
                throw new DependencyInjectionException(sprintf(
                    'Service "%s" declares dependency "%s" more than once.',
                    $definition->id,
                    $dependency,
                ));
            }
            $seen[$dependency] = true;
        }

        $this->definitions[$definition->id] = $definition;
        ksort($this->definitions);
    }

    public function registerAlias(ServiceAlias $alias): void
    {
        $this->assertOpen();
        self::assertIdentifier($alias->alias, 'alias');
        self::assertIdentifier($alias->target, 'alias target');
        self::assertModuleIdentifier($alias->ownerModuleId);

        if ($alias->alias === $alias->target) {
            throw new DependencyInjectionException(sprintf('Alias "%s" cannot target itself.', $alias->alias));
        }
        if (isset($this->definitions[$alias->alias]) || isset($this->aliases[$alias->alias])) {
            throw new DependencyInjectionException(sprintf(
                'Service identifier "%s" is already registered.',
                $alias->alias,
            ));
        }

        $this->aliases[$alias->alias] = $alias;
        ksort($this->aliases);
    }

    public function has(string $id): bool
    {
        return isset($this->definitions[$id]) || isset($this->aliases[$id]);
    }

    /** @return array{array<string, ServiceDefinition>, array<string, ServiceAlias>} */
    public function freeze(): array
    {
        $this->frozen = true;

        return [$this->definitions, $this->aliases];
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    private function assertOpen(): void
    {
        if ($this->frozen) {
            throw new DependencyInjectionException('Service registration is closed.');
        }
    }

    private static function assertIdentifier(string $identifier, string $kind): void
    {
        if ($identifier === '' || preg_match('/[\s\x00-\x1F\x7F]/', $identifier) === 1) {
            throw new DependencyInjectionException(sprintf('Invalid %s identifier.', $kind));
        }
    }

    private static function assertModuleIdentifier(string $moduleId): void
    {
        if (preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*\z/', $moduleId) !== 1) {
            throw new DependencyInjectionException('Invalid owner module identifier.');
        }
    }
}
