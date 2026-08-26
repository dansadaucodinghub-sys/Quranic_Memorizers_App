<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\Tenancy\Domain\Repository\WorkspaceMembershipRepository;
use Qmdb\Modules\Tenancy\Domain\Repository\WorkspaceRepository;
use Qmdb\Modules\Tenancy\Infrastructure\Persistence\MySqlWorkspaceMembershipRepository;
use Qmdb\Modules\Tenancy\Infrastructure\Persistence\MySqlWorkspaceRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class TenancyFoundationModule implements Module
{
    private const ID = 'tenancy.workspaces';

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [new ModuleId('foundation.database'), new ModuleId('identity.accounts')];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            MySqlWorkspaceRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MySqlWorkspaceRepository => new MySqlWorkspaceRepository(
                    ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                ),
            ),
        ));
        $context->alias(WorkspaceRepository::class, MySqlWorkspaceRepository::class);
        $context->service(ServiceDefinition::factory(
            MySqlWorkspaceMembershipRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MySqlWorkspaceMembershipRepository =>
                    new MySqlWorkspaceMembershipRepository(
                        ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                    ),
            ),
        ));
        $context->alias(WorkspaceMembershipRepository::class, MySqlWorkspaceMembershipRepository::class);
    }
}
