<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\ContainerBuilder;
use Qmdb\Shared\DependencyInjection\ContainerCyclicDependencyException;
use Qmdb\Shared\DependencyInjection\DependencyInjectionException;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\InvalidServiceException;
use Qmdb\Shared\DependencyInjection\ServiceAlias;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceNotFoundException;
use Qmdb\Shared\DependencyInjection\UndeclaredDependencyException;
use RuntimeException;
use stdClass;
use Qmdb\Tests\Support\DependencyInjection\FactoryState;
use Qmdb\Tests\Support\DependencyInjection\TypedService;

final class ContainerTest extends TestCase
{
    private const MODULE = 'foundation.core';

    public function testInstanceRegistrationIsSharedAndHasDoesNotInstantiate(): void
    {
        $instance = new stdClass();
        $builder = new ContainerBuilder();
        $builder->register(ServiceDefinition::instance('service.instance', self::MODULE, $instance));
        $container = $builder->build([self::MODULE => []]);

        self::assertTrue($container->has('service.instance'));
        self::assertSame($instance, $container->get('service.instance'));
        self::assertSame($container->get('service.instance'), $container->get('service.instance'));
    }

    public function testFactoryIsLazyAndExecutesOnce(): void
    {
        $state = new FactoryState();
        $builder = new ContainerBuilder();
        $builder->register($this->factory('service.factory', [], static function () use ($state): object {
            ++$state->invocations;

            return new stdClass();
        }));
        $container = $builder->build([self::MODULE => []]);

        self::assertTrue($container->has('service.factory'));
        self::assertSame(0, $state->invocations);
        $first = $container->get('service.factory');
        self::assertSame($first, $container->get('service.factory'));
        self::assertSame(1, $state->invocations);
    }

    public function testAliasChainsResolveToTheSameInstance(): void
    {
        $instance = new stdClass();
        $builder = new ContainerBuilder();
        $builder->register(ServiceDefinition::instance('service.target', self::MODULE, $instance));
        $builder->registerAlias(new ServiceAlias('service.alias', 'service.target', self::MODULE));
        $builder->registerAlias(new ServiceAlias('service.chain', 'service.alias', self::MODULE));
        $container = $builder->build([self::MODULE => []]);

        self::assertTrue($container->has('service.chain'));
        self::assertSame($instance, $container->get('service.chain'));
    }

    public function testUnknownServiceFailsThroughPsrNotFoundException(): void
    {
        $container = (new ContainerBuilder())->build([self::MODULE => []]);

        self::assertFalse($container->has('unknown'));
        $this->expectException(ServiceNotFoundException::class);
        $container->get('unknown');
    }

    public function testFailedFactoryIsNotCachedAndPreservesOriginalFailure(): void
    {
        $state = new FactoryState();
        $failure = new RuntimeException('safe failure');
        $builder = new ContainerBuilder();
        $builder->register($this->factory('service.retry', [], static function () use ($state, $failure): object {
            ++$state->invocations;
            if ($state->invocations === 1) {
                throw $failure;
            }

            return new stdClass();
        }));
        $container = $builder->build([self::MODULE => []]);

        try {
            $container->get('service.retry');
            self::fail('The first factory call must fail.');
        } catch (DependencyInjectionException $exception) {
            self::assertSame($failure, $exception->getPrevious());
        }

        self::assertInstanceOf(stdClass::class, $container->get('service.retry'));
        self::assertSame(2, $state->invocations);
    }

    public function testFactoryReturnTypeIsValidated(): void
    {
        $builder = new ContainerBuilder();
        $builder->register($this->factory(TypedService::class, [], static fn (): object => new stdClass()));
        $container = $builder->build([self::MODULE => []]);

        $this->expectException(InvalidServiceException::class);
        $container->get(TypedService::class);
    }

    public function testUndeclaredFactoryDependencyIsRejected(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(ServiceDefinition::instance('service.allowed', self::MODULE, new stdClass()));
        $builder->register($this->factory(
            'service.consumer',
            [],
            static fn (DependencyResolver $resolver): object => $resolver->get('service.allowed'),
        ));
        $container = $builder->build([self::MODULE => []]);

        $this->expectException(UndeclaredDependencyException::class);
        $container->get('service.consumer');
    }

    public function testMissingDependencyFailsAtBuild(): void
    {
        $builder = new ContainerBuilder();
        $builder->register($this->factory('service.consumer', ['service.missing']));

        $this->expectException(DependencyInjectionException::class);
        $builder->build([self::MODULE => []]);
    }

    public function testTwoServiceCycleFailsAtBuild(): void
    {
        $builder = new ContainerBuilder();
        $builder->register($this->factory('service.a', ['service.b']));
        $builder->register($this->factory('service.b', ['service.a']));

        $this->expectException(ContainerCyclicDependencyException::class);
        $builder->build([self::MODULE => []]);
    }

    public function testAliasCycleFailsAtBuild(): void
    {
        $builder = new ContainerBuilder();
        $builder->registerAlias(new ServiceAlias('alias.a', 'alias.b', self::MODULE));
        $builder->registerAlias(new ServiceAlias('alias.b', 'alias.a', self::MODULE));

        $this->expectException(ContainerCyclicDependencyException::class);
        $builder->build([self::MODULE => []]);
    }

    public function testAliasToMissingTargetFailsAtBuild(): void
    {
        $builder = new ContainerBuilder();
        $builder->registerAlias(new ServiceAlias('alias.a', 'service.missing', self::MODULE));

        $this->expectException(DependencyInjectionException::class);
        $builder->build([self::MODULE => []]);
    }

    public function testDuplicateAndCollidingRegistrationsAreRejected(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(ServiceDefinition::instance('service.a', self::MODULE, new stdClass()));

        $this->expectException(DependencyInjectionException::class);
        $builder->registerAlias(new ServiceAlias('service.a', 'service.target', self::MODULE));
    }

    public function testSelfDependencyAndSelfAliasAreRejected(): void
    {
        $builder = new ContainerBuilder();
        try {
            $builder->register($this->factory('service.a', ['service.a']));
            self::fail('Self-dependency registration must fail.');
        } catch (DependencyInjectionException) {
            self::assertFalse($builder->has('service.a'));
        }

        $this->expectException(DependencyInjectionException::class);
        $builder->registerAlias(new ServiceAlias('alias.a', 'alias.a', self::MODULE));
    }

    public function testRegistrationClosesAfterBuild(): void
    {
        $builder = new ContainerBuilder();
        $builder->build([self::MODULE => []]);

        self::assertTrue($builder->isFrozen());
        $this->expectException(DependencyInjectionException::class);
        $builder->register(ServiceDefinition::instance('late.service', self::MODULE, new stdClass()));
    }

    public function testForbiddenCrossModuleDependencyFailsAtBuild(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(ServiceDefinition::instance('http.service', 'foundation.http', new stdClass()));
        $builder->register($this->factory('core.service', ['http.service']));

        $this->expectException(DependencyInjectionException::class);
        $builder->build([
            'foundation.core' => [],
            'foundation.http' => ['foundation.core'],
        ]);
    }

    public function testUnknownOwnerModuleFailsAtBuild(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(ServiceDefinition::instance(
            'service.orphaned',
            'foundation.unknown',
            new stdClass(),
        ));

        $this->expectException(DependencyInjectionException::class);
        $builder->build([self::MODULE => []]);
    }

    /**
     * @param list<string> $dependencies
     * @param (callable(DependencyResolver): object)|null $factory
     */
    private function factory(string $id, array $dependencies, ?callable $factory = null): ServiceDefinition
    {
        $factory ??= static fn (DependencyResolver $resolver): object => new stdClass();

        return ServiceDefinition::factory(
            $id,
            self::MODULE,
            $dependencies,
            new ClosureServiceFactory($factory(...)),
        );
    }
}
