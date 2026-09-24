<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\Community\Application\RecitationClipRepository;
use Qmdb\Modules\Community\Application\RecitationClipService;
use Qmdb\Modules\Community\Application\CommunityProfileRepository;
use Qmdb\Modules\Community\Application\CommunityProfileService;
use Qmdb\Modules\Community\Application\CommunityOperationReceipts;
use Qmdb\Modules\Community\Application\CommunityReportRepository;
use Qmdb\Modules\Community\Application\CommunityReportService;
use Qmdb\Modules\Community\Application\CommunityModerationRepository;
use Qmdb\Modules\Community\Application\CommunityModerationService;
use Qmdb\Modules\Community\Application\CommunityModerationAppealRepository;
use Qmdb\Modules\Community\Application\CommunityModerationAppealService;
use Qmdb\Modules\Community\Application\CommunityPublicClipReader;
use Qmdb\Modules\Community\Application\CommunityPublicClipService;
use Qmdb\Modules\Community\Application\CommunitySocialRepository;
use Qmdb\Modules\Community\Application\CommunitySocialService;
use Qmdb\Modules\Community\Application\CommunityInteractionRepository;
use Qmdb\Modules\Community\Application\CommunityInteractionService;
use Qmdb\Modules\Community\Application\CommunityCommentRepository;
use Qmdb\Modules\Community\Application\CommunityCommentService;
use Qmdb\Modules\Community\Application\CommunityGlobalReceipts;
use Qmdb\Modules\Community\Application\CommunityFeedCandidates;
use Qmdb\Modules\Community\Application\CommunityFeedService;
use Qmdb\Modules\Community\Application\CommunityNotificationDeliveryService;
use Qmdb\Modules\Community\Application\CommunityNotificationIntentRepository;
use Qmdb\Modules\Community\Application\ScheduledCommunityNotificationTask;
use Qmdb\Modules\Community\Application\CommunityParticipantEligibility;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityParticipantEligibility;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityOperationReceipts;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityReportRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityModerationRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityModerationAppealRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityPublicClipReader;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunitySocialRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityGlobalReceipts;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityInteractionRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityCommentRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityFoundationVerificationRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityFeedCandidates;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityNotificationIntentRepository;
use Qmdb\Modules\Community\Interface\Http\CommunityPublicClipController;
use Qmdb\Modules\Community\Interface\Http\CommunityReportController;
use Qmdb\Modules\Community\Interface\Http\CommunityFeedController;
use Qmdb\Modules\Community\Interface\Http\CommunityProfileController;
use Qmdb\Modules\Community\Interface\Http\CommunityCreatorClipController;
use Qmdb\Modules\Community\Interface\Http\CommunityClipReviewController;
use Qmdb\Modules\Community\Interface\Http\CommunityModerationController;
use Qmdb\Modules\Community\Interface\Http\CommunityModerationAppealController;
use Qmdb\Modules\Community\Interface\Http\CommunitySocialController;
use Qmdb\Modules\Community\Interface\Http\CommunityEngagementController;
use Qmdb\Modules\Community\Interface\Http\CommunityBookmarksController;
use Qmdb\Modules\Community\Interface\Console\CompetitionP10VerifyConsoleCommand;
use Qmdb\Modules\Community\Interface\Console\CommunityNotificationsDeliverConsoleCommand;
use Qmdb\Modules\Community\Interface\Console\CompetitionP10ProductionReadinessConsoleCommand;
use Qmdb\Modules\Community\Interface\Console\CompetitionP10ProductionSmokeConsoleCommand;
use Qmdb\Modules\IdentitySecurityNotifications\Application\Mail\AccountSecurityNotificationNotifier;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Modules\Identity\Infrastructure\Security\ContactCipher;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityProfileRepository;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlRecitationClipRepository;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\MediaCatalog\Application\MediaEvidenceRepository;
use Qmdb\Modules\MediaIngestion\Application\MediaBlobStore;
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
use Qmdb\Shared\Background\Scheduler\FixedIntervalSchedule;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskId;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRegistration;

final readonly class CommunityRecitationClipsModule implements Module
{
    public function id(): ModuleId
    {
        return new ModuleId('community.recitation_clips');
    }

    public function dependencies(): array
    {
        return [new ModuleId('media.catalog'), new ModuleId('media.ingestion'), new ModuleId('media.moderation'),
            new ModuleId('quran.reference_governance'), new ModuleId('people.profiles'),
            new ModuleId('tenancy.context'), new ModuleId('security.authorization'),
            new ModuleId('identity.multifactor'), new ModuleId('security.audit'),
            new ModuleId('identity.sessions'), new ModuleId('foundation.http'),
            new ModuleId('foundation.presentation'), new ModuleId('foundation.database'),
            new ModuleId('identity.security_notifications')];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            MySqlCommunityNotificationIntentRepository::class,
            'community.recitation_clips',
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCommunityNotificationIntentRepository => new MySqlCommunityNotificationIntentRepository(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
            )),
        ));
        $context->alias(CommunityNotificationIntentRepository::class, MySqlCommunityNotificationIntentRepository::class);
        $context->service(ServiceDefinition::factory(
            CommunityNotificationDeliveryService::class,
            'community.recitation_clips',
            [CommunityNotificationIntentRepository::class, TransactionManager::class,
                ContactCipher::class, AccountSecurityNotificationNotifier::class, Clock::class,
                \Qmdb\Modules\IdentityAccess\Configuration\IdentityAccessConfiguration::class],
            new ClosureServiceFactory(static function (DependencyResolver $resolver): CommunityNotificationDeliveryService {
                $configuration = ServiceReference::get(
                    $resolver,
                    \Qmdb\Modules\IdentityAccess\Configuration\IdentityAccessConfiguration::class,
                );
                return new CommunityNotificationDeliveryService(
                    ServiceReference::get($resolver, CommunityNotificationIntentRepository::class),
                    ServiceReference::get($resolver, TransactionManager::class),
                    ServiceReference::get($resolver, ContactCipher::class),
                    ServiceReference::get($resolver, AccountSecurityNotificationNotifier::class),
                    ServiceReference::get($resolver, Clock::class),
                    $configuration->publicBaseUrl->value(),
                );
            }),
        ));
        $context->service(ServiceDefinition::factory(
            ScheduledCommunityNotificationTask::class,
            'community.recitation_clips',
            [CommunityNotificationDeliveryService::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): ScheduledCommunityNotificationTask => new ScheduledCommunityNotificationTask(
                ServiceReference::get($resolver, CommunityNotificationDeliveryService::class),
            )),
        ));
        $context->scheduledTask(new ScheduledTaskRegistration(
            new ScheduledTaskId('community.notifications.deliver'),
            'Deliver due P10 community status notifications.',
            new FixedIntervalSchedule(60),
            ScheduledCommunityNotificationTask::class,
            120,
            'community.recitation_clips',
        ));
        $context->service(ServiceDefinition::factory(
            CommunityNotificationsDeliverConsoleCommand::class,
            'community.recitation_clips',
            [CommunityNotificationDeliveryService::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityNotificationsDeliverConsoleCommand => new CommunityNotificationsDeliverConsoleCommand(
                ServiceReference::get($resolver, CommunityNotificationDeliveryService::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CompetitionP10ProductionReadinessConsoleCommand::class,
            'community.recitation_clips',
            [MySqlCommunityFoundationVerificationRepository::class, \Qmdb\Shared\Background\Scheduler\ScheduledTaskMap::class,
                \Qmdb\Shared\Configuration\ApplicationConfiguration::class,
                \Qmdb\Shared\Configuration\EnvironmentVariables::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP10ProductionReadinessConsoleCommand => new CompetitionP10ProductionReadinessConsoleCommand(
                ServiceReference::get($resolver, MySqlCommunityFoundationVerificationRepository::class),
                ServiceReference::get($resolver, \Qmdb\Shared\Background\Scheduler\ScheduledTaskMap::class),
                ServiceReference::get($resolver, \Qmdb\Shared\Configuration\ApplicationConfiguration::class),
                ServiceReference::get($resolver, \Qmdb\Shared\Configuration\EnvironmentVariables::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CompetitionP10ProductionSmokeConsoleCommand::class,
            'community.recitation_clips',
            [MySqlCommunityFoundationVerificationRepository::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP10ProductionSmokeConsoleCommand => new CompetitionP10ProductionSmokeConsoleCommand(
                ServiceReference::get($resolver, MySqlCommunityFoundationVerificationRepository::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlCommunityModerationAppealRepository::class,
            'community.recitation_clips',
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCommunityModerationAppealRepository => new MySqlCommunityModerationAppealRepository(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
            )),
        ));
        $context->alias(CommunityModerationAppealRepository::class, MySqlCommunityModerationAppealRepository::class);
        $context->service(ServiceDefinition::factory(
            CommunityModerationAppealService::class,
            'community.recitation_clips',
            [CommunityModerationAppealRepository::class, RecitationClipRepository::class,
                CommunityOperationReceipts::class, AuthorizationRequirementGuard::class,
                StepUpGuard::class, IdentityRateLimiter::class,
                IdentityFingerprintGenerator::class, SecurityAuditEventAppender::class,
                CommunityNotificationIntentRepository::class, TransactionManager::class,
                Clock::class, ContactCipher::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityModerationAppealService => new CommunityModerationAppealService(
                ServiceReference::get($resolver, CommunityModerationAppealRepository::class),
                ServiceReference::get($resolver, RecitationClipRepository::class),
                ServiceReference::get($resolver, CommunityOperationReceipts::class),
                ServiceReference::get($resolver, AuthorizationRequirementGuard::class),
                ServiceReference::get($resolver, StepUpGuard::class),
                ServiceReference::get($resolver, IdentityRateLimiter::class),
                ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
                ServiceReference::get($resolver, SecurityAuditEventAppender::class),
                ServiceReference::get($resolver, CommunityNotificationIntentRepository::class),
                ServiceReference::get($resolver, TransactionManager::class),
                ServiceReference::get($resolver, Clock::class),
                ServiceReference::get($resolver, ContactCipher::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CommunityModerationAppealController::class,
            'community.recitation_clips',
            [AuthenticatedRequestGuard::class,
                \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class,
                IdentityCsrf::class, CommunityModerationAppealService::class, IdentityAccessView::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityModerationAppealController => new CommunityModerationAppealController(
                ServiceReference::get($resolver, AuthenticatedRequestGuard::class),
                ServiceReference::get($resolver, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class),
                ServiceReference::get($resolver, IdentityCsrf::class),
                ServiceReference::get($resolver, CommunityModerationAppealService::class),
                ServiceReference::get($resolver, IdentityAccessView::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlCommunityParticipantEligibility::class,
            'community.recitation_clips',
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCommunityParticipantEligibility => new MySqlCommunityParticipantEligibility(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
            )),
        ));
        $context->alias(CommunityParticipantEligibility::class, MySqlCommunityParticipantEligibility::class);
        $context->service(ServiceDefinition::factory(
            MySqlCommunityFeedCandidates::class,
            'community.recitation_clips',
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCommunityFeedCandidates => new MySqlCommunityFeedCandidates(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
            )),
        ));
        $context->alias(CommunityFeedCandidates::class, MySqlCommunityFeedCandidates::class);
        $context->service(ServiceDefinition::factory(
            CommunityFeedService::class,
            'community.recitation_clips',
            [CommunityFeedCandidates::class, CommunityPublicClipReader::class, TransactionManager::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityFeedService => new CommunityFeedService(
                ServiceReference::get($resolver, CommunityFeedCandidates::class),
                ServiceReference::get($resolver, CommunityPublicClipReader::class),
                ServiceReference::get($resolver, TransactionManager::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CommunityFeedController::class,
            'community.recitation_clips',
            [CommunityFeedService::class, AuthenticatedRequestGuard::class, IdentityAccessView::class,
                CommunitySocialService::class, IdentityCsrf::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityFeedController => new CommunityFeedController(
                ServiceReference::get($resolver, CommunityFeedService::class),
                ServiceReference::get($resolver, AuthenticatedRequestGuard::class),
                ServiceReference::get($resolver, IdentityAccessView::class),
                ServiceReference::get($resolver, CommunitySocialService::class),
                ServiceReference::get($resolver, IdentityCsrf::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CommunitySocialController::class,
            'community.recitation_clips',
            [AuthenticatedRequestGuard::class, IdentityCsrf::class,
                CommunitySocialService::class, IdentityAccessView::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunitySocialController => new CommunitySocialController(
                ServiceReference::get($resolver, AuthenticatedRequestGuard::class),
                ServiceReference::get($resolver, IdentityCsrf::class),
                ServiceReference::get($resolver, CommunitySocialService::class),
                ServiceReference::get($resolver, IdentityAccessView::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CommunityEngagementController::class,
            'community.recitation_clips',
            [AuthenticatedRequestGuard::class, IdentityCsrf::class,
                CommunityCommentService::class, CommunityInteractionService::class,
                CommunityPublicClipService::class,
                IdentityAccessView::class, Psr17Factory::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityEngagementController => new CommunityEngagementController(
                ServiceReference::get($resolver, AuthenticatedRequestGuard::class),
                ServiceReference::get($resolver, IdentityCsrf::class),
                ServiceReference::get($resolver, CommunityCommentService::class),
                ServiceReference::get($resolver, CommunityInteractionService::class),
                ServiceReference::get($resolver, CommunityPublicClipService::class),
                ServiceReference::get($resolver, IdentityAccessView::class),
                ServiceReference::get($resolver, Psr17Factory::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CommunityBookmarksController::class,
            'community.recitation_clips',
            [AuthenticatedRequestGuard::class, CommunityInteractionService::class,
                IdentityAccessView::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityBookmarksController => new CommunityBookmarksController(
                ServiceReference::get($resolver, AuthenticatedRequestGuard::class),
                ServiceReference::get($resolver, CommunityInteractionService::class),
                ServiceReference::get($resolver, IdentityAccessView::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CommunityProfileController::class,
            'community.recitation_clips',
            [AuthenticatedRequestGuard::class, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class,
                IdentityCsrf::class, CommunityProfileService::class, IdentityAccessView::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityProfileController => new CommunityProfileController(
                ServiceReference::get($resolver, AuthenticatedRequestGuard::class),
                ServiceReference::get($resolver, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class),
                ServiceReference::get($resolver, IdentityCsrf::class),
                ServiceReference::get($resolver, CommunityProfileService::class),
                ServiceReference::get($resolver, IdentityAccessView::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlCommunityFoundationVerificationRepository::class,
            'community.recitation_clips',
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCommunityFoundationVerificationRepository => new MySqlCommunityFoundationVerificationRepository(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CompetitionP10VerifyConsoleCommand::class,
            'community.recitation_clips',
            [MySqlCommunityFoundationVerificationRepository::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP10VerifyConsoleCommand => new CompetitionP10VerifyConsoleCommand(
                ServiceReference::get($resolver, MySqlCommunityFoundationVerificationRepository::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlCommunityCommentRepository::class,
            'community.recitation_clips',
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCommunityCommentRepository => new MySqlCommunityCommentRepository(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
            )),
        ));
        $context->alias(CommunityCommentRepository::class, MySqlCommunityCommentRepository::class);
        $context->service(ServiceDefinition::factory(
            CommunityCommentService::class,
            'community.recitation_clips',
            [CommunityPublicClipReader::class, CommunityCommentRepository::class,
                CommunityGlobalReceipts::class, IdentityRateLimiter::class,
                IdentityFingerprintGenerator::class, TransactionManager::class, Clock::class,
                CommunityParticipantEligibility::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityCommentService => new CommunityCommentService(
                ServiceReference::get($resolver, CommunityPublicClipReader::class),
                ServiceReference::get($resolver, CommunityCommentRepository::class),
                ServiceReference::get($resolver, CommunityGlobalReceipts::class),
                ServiceReference::get($resolver, IdentityRateLimiter::class),
                ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
                ServiceReference::get($resolver, TransactionManager::class),
                ServiceReference::get($resolver, Clock::class),
                ServiceReference::get($resolver, CommunityParticipantEligibility::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlCommunityInteractionRepository::class,
            'community.recitation_clips',
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCommunityInteractionRepository => new MySqlCommunityInteractionRepository(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
            )),
        ));
        $context->alias(CommunityInteractionRepository::class, MySqlCommunityInteractionRepository::class);
        $context->service(ServiceDefinition::factory(
            CommunityInteractionService::class,
            'community.recitation_clips',
            [CommunityPublicClipReader::class, CommunityInteractionRepository::class,
                CommunityGlobalReceipts::class, IdentityRateLimiter::class,
                IdentityFingerprintGenerator::class, TransactionManager::class, Clock::class,
                CommunityParticipantEligibility::class, CommunityPublicClipService::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityInteractionService => new CommunityInteractionService(
                ServiceReference::get($resolver, CommunityPublicClipReader::class),
                ServiceReference::get($resolver, CommunityInteractionRepository::class),
                ServiceReference::get($resolver, CommunityGlobalReceipts::class),
                ServiceReference::get($resolver, IdentityRateLimiter::class),
                ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
                ServiceReference::get($resolver, TransactionManager::class),
                ServiceReference::get($resolver, Clock::class),
                ServiceReference::get($resolver, CommunityParticipantEligibility::class),
                ServiceReference::get($resolver, CommunityPublicClipService::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlCommunityGlobalReceipts::class,
            'community.recitation_clips',
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCommunityGlobalReceipts => new MySqlCommunityGlobalReceipts(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
            )),
        ));
        $context->alias(CommunityGlobalReceipts::class, MySqlCommunityGlobalReceipts::class);
        $context->service(ServiceDefinition::factory(
            MySqlCommunitySocialRepository::class,
            'community.recitation_clips',
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCommunitySocialRepository => new MySqlCommunitySocialRepository(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
            )),
        ));
        $context->alias(CommunitySocialRepository::class, MySqlCommunitySocialRepository::class);
        $context->service(ServiceDefinition::factory(
            CommunitySocialService::class,
            'community.recitation_clips',
            [CommunitySocialRepository::class, CommunityGlobalReceipts::class,
                IdentityRateLimiter::class, IdentityFingerprintGenerator::class,
                TransactionManager::class, Clock::class, CommunityParticipantEligibility::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunitySocialService => new CommunitySocialService(
                ServiceReference::get($resolver, CommunitySocialRepository::class),
                ServiceReference::get($resolver, CommunityGlobalReceipts::class),
                ServiceReference::get($resolver, IdentityRateLimiter::class),
                ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
                ServiceReference::get($resolver, TransactionManager::class),
                ServiceReference::get($resolver, Clock::class),
                ServiceReference::get($resolver, CommunityParticipantEligibility::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlRecitationClipRepository::class,
            'community.recitation_clips',
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlRecitationClipRepository => new MySqlRecitationClipRepository(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
            )),
        ));
        $context->alias(RecitationClipRepository::class, MySqlRecitationClipRepository::class);
        $context->service(ServiceDefinition::factory(
            MySqlCommunityProfileRepository::class,
            'community.recitation_clips',
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCommunityProfileRepository => new MySqlCommunityProfileRepository(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
            )),
        ));
        $context->alias(CommunityProfileRepository::class, MySqlCommunityProfileRepository::class);
        $context->service(ServiceDefinition::factory(
            MySqlCommunityOperationReceipts::class,
            'community.recitation_clips',
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCommunityOperationReceipts => new MySqlCommunityOperationReceipts(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
            )),
        ));
        $context->alias(CommunityOperationReceipts::class, MySqlCommunityOperationReceipts::class);
        $context->service(ServiceDefinition::factory(
            MySqlCommunityReportRepository::class,
            'community.recitation_clips',
            [DatabaseConnectionProvider::class, RecitationClipRepository::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCommunityReportRepository => new MySqlCommunityReportRepository(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                ServiceReference::get($resolver, RecitationClipRepository::class),
            )),
        ));
        $context->alias(CommunityReportRepository::class, MySqlCommunityReportRepository::class);
        $context->service(ServiceDefinition::factory(
            MySqlCommunityModerationRepository::class,
            'community.recitation_clips',
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCommunityModerationRepository => new MySqlCommunityModerationRepository(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
            )),
        ));
        $context->alias(CommunityModerationRepository::class, MySqlCommunityModerationRepository::class);
        $context->service(ServiceDefinition::factory(
            MySqlCommunityPublicClipReader::class,
            'community.recitation_clips',
            [DatabaseConnectionProvider::class, RecitationClipRepository::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCommunityPublicClipReader => new MySqlCommunityPublicClipReader(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                ServiceReference::get($resolver, RecitationClipRepository::class),
            )),
        ));
        $context->alias(CommunityPublicClipReader::class, MySqlCommunityPublicClipReader::class);
        $context->service(ServiceDefinition::factory(
            CommunityPublicClipService::class,
            'community.recitation_clips',
            [CommunityPublicClipReader::class, MediaEvidenceRepository::class,
                MediaBlobStore::class, TransactionManager::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityPublicClipService => new CommunityPublicClipService(
                ServiceReference::get($resolver, CommunityPublicClipReader::class),
                ServiceReference::get($resolver, MediaEvidenceRepository::class),
                ServiceReference::get($resolver, MediaBlobStore::class),
                ServiceReference::get($resolver, TransactionManager::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CommunityPublicClipController::class,
            'community.recitation_clips',
            [CommunityPublicClipService::class, AuthenticatedRequestGuard::class,
                IdentityAccessView::class, Psr17Factory::class,
                CommunityInteractionService::class, IdentityCsrf::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityPublicClipController => new CommunityPublicClipController(
                ServiceReference::get($resolver, CommunityPublicClipService::class),
                ServiceReference::get($resolver, AuthenticatedRequestGuard::class),
                ServiceReference::get($resolver, IdentityAccessView::class),
                ServiceReference::get($resolver, Psr17Factory::class),
                ServiceReference::get($resolver, CommunityInteractionService::class),
                ServiceReference::get($resolver, IdentityCsrf::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CommunityReportController::class,
            'community.recitation_clips',
            [AuthenticatedRequestGuard::class, IdentityCsrf::class,
                CommunityPublicClipService::class, CommunityReportService::class,
                IdentityAccessView::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityReportController => new CommunityReportController(
                ServiceReference::get($resolver, AuthenticatedRequestGuard::class),
                ServiceReference::get($resolver, IdentityCsrf::class),
                ServiceReference::get($resolver, CommunityPublicClipService::class),
                ServiceReference::get($resolver, CommunityReportService::class),
                ServiceReference::get($resolver, IdentityAccessView::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CommunityModerationService::class,
            'community.recitation_clips',
            [CommunityModerationRepository::class, RecitationClipRepository::class,
                CommunityCommentRepository::class,
                CommunityOperationReceipts::class, AuthorizationRequirementGuard::class,
                StepUpGuard::class, IdentityRateLimiter::class,
                IdentityFingerprintGenerator::class, SecurityAuditEventAppender::class,
                CommunityNotificationIntentRepository::class, TransactionManager::class,
                Clock::class, ContactCipher::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityModerationService => new CommunityModerationService(
                ServiceReference::get($resolver, CommunityModerationRepository::class),
                ServiceReference::get($resolver, RecitationClipRepository::class),
                ServiceReference::get($resolver, CommunityCommentRepository::class),
                ServiceReference::get($resolver, CommunityOperationReceipts::class),
                ServiceReference::get($resolver, AuthorizationRequirementGuard::class),
                ServiceReference::get($resolver, StepUpGuard::class),
                ServiceReference::get($resolver, IdentityRateLimiter::class),
                ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
                ServiceReference::get($resolver, SecurityAuditEventAppender::class),
                ServiceReference::get($resolver, CommunityNotificationIntentRepository::class),
                ServiceReference::get($resolver, TransactionManager::class),
                ServiceReference::get($resolver, Clock::class),
                ServiceReference::get($resolver, ContactCipher::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CommunityReportService::class,
            'community.recitation_clips',
            [CommunityReportRepository::class, CommunityOperationReceipts::class,
                ContactCipher::class, IdentityRateLimiter::class,
                IdentityFingerprintGenerator::class, SecurityAuditEventAppender::class,
                CommunityNotificationIntentRepository::class, TransactionManager::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityReportService => new CommunityReportService(
                ServiceReference::get($resolver, CommunityReportRepository::class),
                ServiceReference::get($resolver, CommunityOperationReceipts::class),
                ServiceReference::get($resolver, ContactCipher::class),
                ServiceReference::get($resolver, IdentityRateLimiter::class),
                ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
                ServiceReference::get($resolver, SecurityAuditEventAppender::class),
                ServiceReference::get($resolver, CommunityNotificationIntentRepository::class),
                ServiceReference::get($resolver, TransactionManager::class),
                ServiceReference::get($resolver, Clock::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CommunityProfileService::class,
            'community.recitation_clips',
            [CommunityProfileRepository::class, AuthorizationRequirementGuard::class,
                TransactionManager::class, Clock::class, CommunityGlobalReceipts::class,
                IdentityRateLimiter::class, IdentityFingerprintGenerator::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityProfileService => new CommunityProfileService(
                ServiceReference::get($resolver, CommunityProfileRepository::class),
                ServiceReference::get($resolver, AuthorizationRequirementGuard::class),
                ServiceReference::get($resolver, TransactionManager::class),
                ServiceReference::get($resolver, Clock::class),
                ServiceReference::get($resolver, CommunityGlobalReceipts::class),
                ServiceReference::get($resolver, IdentityRateLimiter::class),
                ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            RecitationClipService::class,
            'community.recitation_clips',
            [RecitationClipRepository::class, CommunityOperationReceipts::class,
                AuthorizationRequirementGuard::class, StepUpGuard::class,
                IdentityRateLimiter::class, IdentityFingerprintGenerator::class,
                SecurityAuditEventAppender::class, CommunityNotificationIntentRepository::class,
                TransactionManager::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): RecitationClipService => new RecitationClipService(
                ServiceReference::get($resolver, RecitationClipRepository::class),
                ServiceReference::get($resolver, CommunityOperationReceipts::class),
                ServiceReference::get($resolver, AuthorizationRequirementGuard::class),
                ServiceReference::get($resolver, StepUpGuard::class),
                ServiceReference::get($resolver, IdentityRateLimiter::class),
                ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
                ServiceReference::get($resolver, SecurityAuditEventAppender::class),
                ServiceReference::get($resolver, CommunityNotificationIntentRepository::class),
                ServiceReference::get($resolver, TransactionManager::class),
                ServiceReference::get($resolver, Clock::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CommunityCreatorClipController::class,
            'community.recitation_clips',
            [AuthenticatedRequestGuard::class, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class,
                IdentityCsrf::class, RecitationClipService::class, IdentityAccessView::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityCreatorClipController => new CommunityCreatorClipController(
                ServiceReference::get($resolver, AuthenticatedRequestGuard::class),
                ServiceReference::get($resolver, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class),
                ServiceReference::get($resolver, IdentityCsrf::class),
                ServiceReference::get($resolver, RecitationClipService::class),
                ServiceReference::get($resolver, IdentityAccessView::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CommunityClipReviewController::class,
            'community.recitation_clips',
            [AuthenticatedRequestGuard::class, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class,
                IdentityCsrf::class, RecitationClipService::class, IdentityAccessView::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityClipReviewController => new CommunityClipReviewController(
                ServiceReference::get($resolver, AuthenticatedRequestGuard::class),
                ServiceReference::get($resolver, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class),
                ServiceReference::get($resolver, IdentityCsrf::class),
                ServiceReference::get($resolver, RecitationClipService::class),
                ServiceReference::get($resolver, IdentityAccessView::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CommunityModerationController::class,
            'community.recitation_clips',
            [AuthenticatedRequestGuard::class, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class,
                IdentityCsrf::class, CommunityModerationService::class, IdentityAccessView::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CommunityModerationController => new CommunityModerationController(
                ServiceReference::get($resolver, AuthenticatedRequestGuard::class),
                ServiceReference::get($resolver, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class),
                ServiceReference::get($resolver, IdentityCsrf::class),
                ServiceReference::get($resolver, CommunityModerationService::class),
                ServiceReference::get($resolver, IdentityAccessView::class),
            )),
        ));
    }
}
