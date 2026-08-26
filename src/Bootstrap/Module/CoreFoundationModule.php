<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Bootstrap\ApplicationMetadata;
use Qmdb\Bootstrap\RuntimeEnvironment;
use Qmdb\Bootstrap\RuntimeRequirements;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Identifier\RuntimeIdentifierGenerator;
use Qmdb\Shared\Identifier\SecureRandomRuntimeIdentifierGenerator;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Security\Secrets\EnvironmentSecretsProvider;
use Qmdb\Shared\Security\Secrets\SecretsProvider;
use Qmdb\Shared\Time\Clock;
use Qmdb\Shared\Time\SystemClock;

final readonly class CoreFoundationModule implements Module
{
    private const ID = 'foundation.core';

    public function __construct(
        private ApplicationConfiguration $configuration,
        private EnvironmentVariables $environmentVariables,
        private RuntimeEnvironment $runtimeEnvironment,
    ) {
    }

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(
            ApplicationMetadata::class,
            self::ID,
            ApplicationMetadata::current(),
        ));
        $context->service(ServiceDefinition::instance(
            ApplicationConfiguration::class,
            self::ID,
            $this->configuration,
        ));
        $context->service(ServiceDefinition::instance(
            EnvironmentVariables::class,
            self::ID,
            $this->environmentVariables,
        ));
        $context->service(ServiceDefinition::instance(
            RuntimeEnvironment::class,
            self::ID,
            $this->runtimeEnvironment,
        ));
        $context->service(ServiceDefinition::instance(
            RuntimeRequirements::class,
            self::ID,
            new RuntimeRequirements(),
        ));
        $context->service(ServiceDefinition::factory(
            SystemClock::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SystemClock => new SystemClock(),
            ),
        ));
        $context->alias(Clock::class, SystemClock::class);
        $context->service(ServiceDefinition::factory(
            SecureRandomRuntimeIdentifierGenerator::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SecureRandomRuntimeIdentifierGenerator =>
                    new SecureRandomRuntimeIdentifierGenerator(),
            ),
        ));
        $context->alias(RuntimeIdentifierGenerator::class, SecureRandomRuntimeIdentifierGenerator::class);
        $context->service(ServiceDefinition::factory(
            EnvironmentSecretsProvider::class,
            self::ID,
            [EnvironmentVariables::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): EnvironmentSecretsProvider =>
                    new EnvironmentSecretsProvider(ServiceReference::get($resolver, EnvironmentVariables::class)),
            ),
        ));
        $context->alias(SecretsProvider::class, EnvironmentSecretsProvider::class);
    }
}
