<?php

declare(strict_types=1);

namespace Qmdb\Shared\DependencyInjection;

final class ContainerBuilder
{
    public function __construct(private readonly ServiceDefinitionRegistry $registry = new ServiceDefinitionRegistry())
    {
    }

    public function register(ServiceDefinition $definition): void
    {
        $this->registry->register($definition);
    }

    public function registerAlias(ServiceAlias $alias): void
    {
        $this->registry->registerAlias($alias);
    }

    public function has(string $id): bool
    {
        return $this->registry->has($id);
    }

    public function isFrozen(): bool
    {
        return $this->registry->isFrozen();
    }

    /** @param array<string, list<string>> $moduleDependencies */
    public function build(array $moduleDependencies): CompiledContainer
    {
        [$definitions, $aliasDefinitions] = $this->registry->freeze();
        $aliases = $this->validateAliases($definitions, $aliasDefinitions, $moduleDependencies);
        $this->validateDefinitions($definitions, $aliases, $moduleDependencies);

        return new CompiledContainer($definitions, $aliases);
    }

    /**
     * @param array<string, ServiceDefinition> $definitions
     * @param array<string, ServiceAlias> $aliasDefinitions
     * @param array<string, list<string>> $moduleDependencies
     * @return array<string, string>
     */
    private function validateAliases(
        array $definitions,
        array $aliasDefinitions,
        array $moduleDependencies,
    ): array {
        $resolved = [];
        foreach ($aliasDefinitions as $id => $alias) {
            if (!array_key_exists($alias->ownerModuleId, $moduleDependencies)) {
                throw new DependencyInjectionException(sprintf(
                    'Alias "%s" declares unknown owner module "%s".',
                    $id,
                    $alias->ownerModuleId,
                ));
            }

            $seen = [];
            $current = $id;
            while (isset($aliasDefinitions[$current])) {
                if (isset($seen[$current])) {
                    throw new ContainerCyclicDependencyException(sprintf(
                        'Circular service alias detected at "%s".',
                        $current,
                    ));
                }
                $seen[$current] = true;
                $current = $aliasDefinitions[$current]->target;
            }

            if (!isset($definitions[$current])) {
                throw new DependencyInjectionException(sprintf(
                    'Alias "%s" targets unknown service "%s".',
                    $id,
                    $current,
                ));
            }
            $resolved[$id] = $current;
            $this->assertModuleAccess(
                $alias->ownerModuleId,
                $definitions[$current]->ownerModuleId,
                $moduleDependencies,
                $id,
                $current,
            );
        }

        ksort($resolved);

        return $resolved;
    }

    /**
     * @param array<string, ServiceDefinition> $definitions
     * @param array<string, string> $aliases
     * @param array<string, list<string>> $moduleDependencies
     */
    private function validateDefinitions(array $definitions, array $aliases, array $moduleDependencies): void
    {
        foreach ($definitions as $id => $definition) {
            if (!array_key_exists($definition->ownerModuleId, $moduleDependencies)) {
                throw new DependencyInjectionException(sprintf(
                    'Service "%s" declares unknown owner module "%s".',
                    $id,
                    $definition->ownerModuleId,
                ));
            }

            foreach ($definition->dependencies as $dependency) {
                $target = $aliases[$dependency] ?? $dependency;
                if (!isset($definitions[$target])) {
                    throw new DependencyInjectionException(sprintf(
                        'Service "%s" depends on unknown service "%s".',
                        $id,
                        $dependency,
                    ));
                }

                $this->assertModuleAccess(
                    $definition->ownerModuleId,
                    $definitions[$target]->ownerModuleId,
                    $moduleDependencies,
                    $id,
                    $target,
                );
            }
        }

        $temporary = [];
        $permanent = [];
        foreach (array_keys($definitions) as $id) {
            $this->visit($id, $definitions, $aliases, $temporary, $permanent);
        }
    }

    /**
     * @param array<string, ServiceDefinition> $definitions
     * @param array<string, string> $aliases
     * @param array<string, true> $temporary
     * @param array<string, true> $permanent
     */
    private function visit(
        string $id,
        array $definitions,
        array $aliases,
        array &$temporary,
        array &$permanent,
    ): void {
        if (isset($permanent[$id])) {
            return;
        }
        if (isset($temporary[$id])) {
            throw new ContainerCyclicDependencyException(sprintf(
                'Circular service dependency detected at "%s".',
                $id,
            ));
        }

        $temporary[$id] = true;
        foreach ($definitions[$id]->dependencies as $dependency) {
            $this->visit($aliases[$dependency] ?? $dependency, $definitions, $aliases, $temporary, $permanent);
        }
        unset($temporary[$id]);
        $permanent[$id] = true;
    }

    /** @param array<string, list<string>> $moduleDependencies */
    private function assertModuleAccess(
        string $consumerModule,
        string $providerModule,
        array $moduleDependencies,
        string $consumerService,
        string $providerService,
    ): void {
        if ($consumerModule === $providerModule) {
            return;
        }

        $queue = $moduleDependencies[$consumerModule] ?? [];
        $seen = [];
        while ($queue !== []) {
            $candidate = array_shift($queue);
            if ($candidate === $providerModule) {
                return;
            }
            if (isset($seen[$candidate])) {
                continue;
            }
            $seen[$candidate] = true;
            foreach ($moduleDependencies[$candidate] ?? [] as $dependency) {
                $queue[] = $dependency;
            }
        }

        throw new DependencyInjectionException(sprintf(
            'Service "%s" in module "%s" cannot depend on service "%s" in module "%s".',
            $consumerService,
            $consumerModule,
            $providerService,
            $providerModule,
        ));
    }
}
