<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Module;

use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Application\Command\CommandHandlerRegistry;
use Qmdb\Shared\Application\Event\DomainEventSubscriberRegistry;
use Qmdb\Shared\Application\Query\Query;
use Qmdb\Shared\Application\Query\QueryHandlerRegistry;
use Qmdb\Shared\DependencyInjection\ContainerBuilder;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\Module\ModuleDependencyException;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Module\ModuleRegistry;
use stdClass;
use Qmdb\Tests\Support\Module\AlphaTestModule;
use Qmdb\Tests\Support\Module\ApplicationTestModule;
use Qmdb\Tests\Support\Module\BetaTestModule;
use Qmdb\Tests\Support\Module\CoreTestModule;
use Qmdb\Tests\Support\Module\GammaTestModule;
use Qmdb\Tests\Support\Module\ModuleState;
use Qmdb\Tests\Support\Module\TestQuery;
use Qmdb\Tests\Support\Module\ZetaTestModule;

final class ModuleRegistryTest extends TestCase
{
    public function testDependenciesRegisterBeforeDependentsAndIndependentModulesAreSorted(): void
    {
        $registry = new ModuleRegistry([
            new ZetaTestModule(new ModuleId('foundation.zeta')),
            new ApplicationTestModule(
                new ModuleId('foundation.application'),
                [new ModuleId('foundation.core')],
            ),
            new CoreTestModule(new ModuleId('foundation.core')),
            new AlphaTestModule(new ModuleId('foundation.alpha')),
        ]);

        self::assertSame([
            'foundation.alpha',
            'foundation.core',
            'foundation.application',
            'foundation.zeta',
        ], $registry->orderedModuleIds());
    }

    public function testDuplicateModuleIdIsRejected(): void
    {
        $this->expectException(ModuleDependencyException::class);
        new ModuleRegistry([
            new CoreTestModule(new ModuleId('foundation.core')),
            new AlphaTestModule(new ModuleId('foundation.core')),
        ]);
    }

    public function testSameModuleClassCannotSupplyConflictingIds(): void
    {
        $this->expectException(ModuleDependencyException::class);
        new ModuleRegistry([
            new AlphaTestModule(new ModuleId('foundation.alpha')),
            new AlphaTestModule(new ModuleId('foundation.beta')),
        ]);
    }

    public function testSelfDependencyIsRejected(): void
    {
        $this->expectException(ModuleDependencyException::class);
        new ModuleRegistry([
            new AlphaTestModule(
                new ModuleId('foundation.alpha'),
                [new ModuleId('foundation.alpha')],
            ),
        ]);
    }

    public function testMissingDependencyIsRejectedSafely(): void
    {
        $this->expectException(ModuleDependencyException::class);
        new ModuleRegistry([
            new AlphaTestModule(
                new ModuleId('foundation.alpha'),
                [new ModuleId('foundation.missing')],
            ),
        ]);
    }

    public function testTwoModuleCycleIsRejected(): void
    {
        $this->expectException(ModuleDependencyException::class);
        new ModuleRegistry([
            new AlphaTestModule(
                new ModuleId('foundation.alpha'),
                [new ModuleId('foundation.beta')],
            ),
            new BetaTestModule(
                new ModuleId('foundation.beta'),
                [new ModuleId('foundation.alpha')],
            ),
        ]);
    }

    public function testMultiModuleCycleIsRejected(): void
    {
        $this->expectException(ModuleDependencyException::class);
        new ModuleRegistry([
            new AlphaTestModule(new ModuleId('foundation.alpha'), [new ModuleId('foundation.beta')]),
            new BetaTestModule(new ModuleId('foundation.beta'), [new ModuleId('foundation.gamma')]),
            new GammaTestModule(new ModuleId('foundation.gamma'), [new ModuleId('foundation.alpha')]),
        ]);
    }

    public function testModuleRegistrationExecutesOnceAndRegistryFreezes(): void
    {
        $state = new ModuleState();
        $module = new CoreTestModule(
            new ModuleId('foundation.core'),
            register: static function (ModuleRegistrationContext $context) use ($state): void {
                ++$state->registrations;
                $context->service(ServiceDefinition::instance(
                    'service.core',
                    'foundation.core',
                    new stdClass(),
                ));
            },
        );
        $registry = new ModuleRegistry([$module]);
        $builder = new ContainerBuilder();
        $registry->compile($builder);

        self::assertSame(1, $state->registrations);
        $this->expectException(ModuleDependencyException::class);
        $registry->compile($builder);
    }

    public function testRegistrationContextRecordsAndEnforcesServiceOwnership(): void
    {
        $context = new ModuleRegistrationContext(
            new ModuleId('foundation.core'),
            new ContainerBuilder(),
            new CommandHandlerRegistry(),
            new QueryHandlerRegistry(),
            new DomainEventSubscriberRegistry(),
        );

        $this->expectException(ModuleDependencyException::class);
        $context->service(ServiceDefinition::instance(
            'service.invalid',
            'foundation.http',
            new stdClass(),
        ));
    }

    public function testRegistrationContextRejectsChangesAfterFreeze(): void
    {
        $context = new ModuleRegistrationContext(
            new ModuleId('foundation.core'),
            new ContainerBuilder(),
            new CommandHandlerRegistry(),
            new QueryHandlerRegistry(),
            new DomainEventSubscriberRegistry(),
        );
        $context->freeze();

        self::assertTrue($context->isFrozen());
        $this->expectException(ModuleDependencyException::class);
        $context->alias('service.alias', 'service.target');
    }

    public function testMissingHandlerServicePreventsModuleCompilation(): void
    {
        $module = new ApplicationTestModule(
            new ModuleId('foundation.application'),
            register: static fn (ModuleRegistrationContext $context) =>
                $context->queryHandler(TestQuery::class, 'handler.missing'),
        );

        $this->expectException(ModuleDependencyException::class);
        (new ModuleRegistry([$module]))->compile(new ContainerBuilder());
    }
}
