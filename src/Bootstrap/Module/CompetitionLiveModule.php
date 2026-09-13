<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Modules\CompetitionLive\Domain\LiveParticipantLifecycle;
use Qmdb\Modules\CompetitionLive\Domain\LiveSessionLifecycle;
use Qmdb\Modules\CompetitionLive\Application\CompetitionLiveRuntimeRepository;
use Qmdb\Modules\CompetitionLive\Application\CompetitionLiveSessionWorkflowService;
use Qmdb\Modules\CompetitionLive\Application\CompetitionLivePublicReadRepository;
use Qmdb\Modules\CompetitionLive\Infrastructure\Persistence\MySqlCompetitionLiveRuntimeRepository;
use Qmdb\Modules\CompetitionLive\Infrastructure\Persistence\MySqlCompetitionLivePublicReadRepository;
use Qmdb\Modules\CompetitionLive\Interface\Http\CompetitionPublicLiveController;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Time\Clock;

final readonly class CompetitionLiveModule implements Module
{
    public function id(): ModuleId
    {
        return new ModuleId('competition.live_operations');
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('competition.configuration'),
            new ModuleId('competition.registration'),
            new ModuleId('competition.judging'),
            new ModuleId('competition.scoring'),
            new ModuleId('competition.results'),
            new ModuleId('foundation.database'),
            new ModuleId('security.authorization'),
            new ModuleId('security.audit'),
            new ModuleId('security.web'),
            new ModuleId('tenancy.context'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(MySqlCompetitionLiveRuntimeRepository::class, 'competition.live_operations', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCompetitionLiveRuntimeRepository => new MySqlCompetitionLiveRuntimeRepository(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
        $context->alias(CompetitionLiveRuntimeRepository::class, MySqlCompetitionLiveRuntimeRepository::class);
        $context->service(ServiceDefinition::factory(MySqlCompetitionLivePublicReadRepository::class, 'competition.live_operations', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCompetitionLivePublicReadRepository => new MySqlCompetitionLivePublicReadRepository(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
        $context->alias(CompetitionLivePublicReadRepository::class, MySqlCompetitionLivePublicReadRepository::class);
        $context->service(ServiceDefinition::instance(LiveSessionLifecycle::class, 'competition.live_operations', new LiveSessionLifecycle()));
        $context->service(ServiceDefinition::instance(LiveParticipantLifecycle::class, 'competition.live_operations', new LiveParticipantLifecycle()));
        $context->service(ServiceDefinition::factory(CompetitionLiveSessionWorkflowService::class, 'competition.live_operations', [CompetitionLiveRuntimeRepository::class, LiveSessionLifecycle::class, IdentityFingerprintGenerator::class, TransactionManager::class, Clock::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionLiveSessionWorkflowService => new CompetitionLiveSessionWorkflowService(ServiceReference::get($resolver, CompetitionLiveRuntimeRepository::class), ServiceReference::get($resolver, LiveSessionLifecycle::class), ServiceReference::get($resolver, IdentityFingerprintGenerator::class), ServiceReference::get($resolver, TransactionManager::class), ServiceReference::get($resolver, Clock::class)))));
        $context->service(ServiceDefinition::factory(CompetitionPublicLiveController::class, 'competition.live_operations', [CompetitionLivePublicReadRepository::class, Psr17Factory::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionPublicLiveController => new CompetitionPublicLiveController(ServiceReference::get($resolver, CompetitionLivePublicReadRepository::class), ServiceReference::get($resolver, Psr17Factory::class)))));
    }
}
