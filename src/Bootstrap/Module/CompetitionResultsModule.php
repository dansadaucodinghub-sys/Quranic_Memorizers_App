<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\CompetitionResults\Interface\Console\CompetitionP6VerifyConsoleCommand;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class CompetitionResultsModule implements Module
{
    public function id(): ModuleId { return new ModuleId('competition.results'); }
    public function dependencies(): array { return [new ModuleId('competition.scoring'), new ModuleId('foundation.database'), new ModuleId('security.authorization'), new ModuleId('security.audit'), new ModuleId('security.web'), new ModuleId('tenancy.context')]; }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(CompetitionP6VerifyConsoleCommand::class, 'competition.results', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP6VerifyConsoleCommand => new CompetitionP6VerifyConsoleCommand(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
    }
}
