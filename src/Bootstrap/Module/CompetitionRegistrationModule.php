<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\CompetitionRegistration\Interface\Console\CompetitionRegistrationVerifyConsoleCommand;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class CompetitionRegistrationModule implements Module
{
    public function id(): ModuleId
    {
        return new ModuleId('competition.registration');
    }
    public function dependencies(): array
    {
        return [new ModuleId('competition.configuration'), new ModuleId('foundation.core'), new ModuleId('foundation.database'), new ModuleId('foundation.http'), new ModuleId('security.authorization'), new ModuleId('security.audit'), new ModuleId('security.web'), new ModuleId('tenancy.context'), new ModuleId('identity.accounts'), new ModuleId('identity.sessions'), new ModuleId('people.profiles'), new ModuleId('organizations.affiliations')];
    }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(CompetitionRegistrationVerifyConsoleCommand::class, 'competition.registration', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionRegistrationVerifyConsoleCommand => new CompetitionRegistrationVerifyConsoleCommand(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
    }
}
