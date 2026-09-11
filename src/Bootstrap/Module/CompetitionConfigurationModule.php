<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\CompetitionConfiguration\Interface\Console\CompetitionConfigurationVerifyConsoleCommand;
use Qmdb\Modules\CompetitionConfiguration\Interface\Console\CompetitionP5VerifyConsoleCommand;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class CompetitionConfigurationModule implements Module
{
    public function id(): ModuleId
    {
        return new ModuleId('competition.configuration');
    }
    public function dependencies(): array
    {
        return [new ModuleId('foundation.core'), new ModuleId('foundation.database'), new ModuleId('foundation.schema'), new ModuleId('foundation.http'), new ModuleId('foundation.presentation'), new ModuleId('security.authorization'), new ModuleId('security.audit'), new ModuleId('security.web'), new ModuleId('tenancy.context'), new ModuleId('organizations.registry'), new ModuleId('reference.geography'), new ModuleId('quran.reference_governance')];
    }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(CompetitionConfigurationVerifyConsoleCommand::class, 'competition.configuration', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionConfigurationVerifyConsoleCommand => new CompetitionConfigurationVerifyConsoleCommand(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
        $context->service(ServiceDefinition::factory(CompetitionP5VerifyConsoleCommand::class, 'competition.configuration', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP5VerifyConsoleCommand => new CompetitionP5VerifyConsoleCommand(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
    }
}
