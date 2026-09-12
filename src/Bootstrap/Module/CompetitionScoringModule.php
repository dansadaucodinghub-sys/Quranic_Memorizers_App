<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\CompetitionScoring\Application\CompetitionScoreSheetRepository;
use Qmdb\Modules\CompetitionScoring\Application\CompetitionScoreSheetService;
use Qmdb\Modules\CompetitionScoring\Domain\ScoreSheetCalculator;
use Qmdb\Modules\CompetitionScoring\Infrastructure\Persistence\MySqlCompetitionScoreSheetRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
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

final readonly class CompetitionScoringModule implements Module
{
    public function id(): ModuleId { return new ModuleId('competition.scoring'); }
    public function dependencies(): array { return [new ModuleId('competition.judging'), new ModuleId('foundation.database'), new ModuleId('security.authorization'), new ModuleId('security.audit'), new ModuleId('tenancy.context')]; }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(MySqlCompetitionScoreSheetRepository::class, 'competition.scoring', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCompetitionScoreSheetRepository => new MySqlCompetitionScoreSheetRepository(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
        $context->alias(CompetitionScoreSheetRepository::class, MySqlCompetitionScoreSheetRepository::class);
        $context->service(ServiceDefinition::instance(ScoreSheetCalculator::class, 'competition.scoring', new ScoreSheetCalculator()));
        $context->service(ServiceDefinition::factory(CompetitionScoreSheetService::class, 'competition.scoring', [CompetitionScoreSheetRepository::class,ScoreSheetCalculator::class,AuthorizationRequirementGuard::class,IdentityRateLimiter::class,IdentityFingerprintGenerator::class,TransactionManager::class,Clock::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionScoreSheetService => new CompetitionScoreSheetService(ServiceReference::get($resolver, CompetitionScoreSheetRepository::class), ServiceReference::get($resolver, ScoreSheetCalculator::class), ServiceReference::get($resolver, AuthorizationRequirementGuard::class), ServiceReference::get($resolver, IdentityRateLimiter::class), ServiceReference::get($resolver, IdentityFingerprintGenerator::class), ServiceReference::get($resolver, TransactionManager::class), ServiceReference::get($resolver, Clock::class)))));
    }
}
