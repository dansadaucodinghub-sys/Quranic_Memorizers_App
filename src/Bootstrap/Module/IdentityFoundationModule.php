<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\Identity\Domain\Repository\AccountRepository;
use Qmdb\Modules\Identity\Infrastructure\Persistence\MySqlAccountRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class IdentityFoundationModule implements Module
{
    private const ID = 'identity.accounts';

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [new ModuleId('foundation.database')];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            MySqlAccountRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MySqlAccountRepository => new MySqlAccountRepository(
                    ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                ),
            ),
        ));
        $context->alias(AccountRepository::class, MySqlAccountRepository::class);
    }
}
