<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessSchemaVerifier;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessReadinessCheck;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessAuthorizationRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessAuthorizationService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessActivationRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessActivationService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessMaintenanceRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessMaintenanceService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessLifecycleRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessApprovalService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessRequestCancellationService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessNotificationService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessEndService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessRevocationService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessReviewService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\BreakGlassActivationService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessContextRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessContextResolver;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessHttpRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessRequestRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessRequestService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\ScheduledPrivilegedAccessMaintenanceTask;
use Qmdb\Modules\SecurityPrivilegedAccess\Configuration\PrivilegedAccessConfiguration;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Persistence\MySqlPrivilegedAccessMaintenanceRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Persistence\MySqlPrivilegedAccessAuthorizationRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Persistence\MySqlPrivilegedAccessActivationRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Persistence\MySqlPrivilegedAccessLifecycleRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Persistence\MySqlPrivilegedAccessContextRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Persistence\MySqlPrivilegedAccessHttpRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Persistence\MySqlPrivilegedAccessRequestRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Persistence\MySqlPrivilegedAccessSchemaVerifier;
use Qmdb\Modules\SecurityPrivilegedAccess\Interface\Console\PrivilegedAccessVerifyConsoleCommand;
use Qmdb\Modules\SecurityPrivilegedAccess\Interface\Http\PrivilegedAccessContextMiddleware;
use Qmdb\Modules\SecurityPrivilegedAccess\Interface\Http\PrivilegedAccessController;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Background\Scheduler\FixedIntervalSchedule;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskId;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRegistration;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Time\Clock;
use Qmdb\Modules\SecurityAuthorization\Application\AuthenticationAssuranceComparator;
use Qmdb\Modules\SecurityAuthorization\Application\BaseRoleAuthorizationGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationService;
use Qmdb\Modules\SecurityAuthorization\Application\RoleBasedAuthorizationService;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalog;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\MultiFactorNotificationTargetRepository;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\TenancyContext\Domain\Repository\SessionTenantContextRepository;

final readonly class SecurityPrivilegedAccessModule implements Module
{
    private const string ID = 'security.privileged_access';

    public function __construct(private PrivilegedAccessConfiguration $configuration)
    {
    }

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('foundation.core'), new ModuleId('foundation.database'), new ModuleId('foundation.schema'),
            new ModuleId('security.authorization'), new ModuleId('tenancy.context'),
            new ModuleId('security.audit'),
            new ModuleId('identity.sessions'), new ModuleId('identity.multifactor'),
            new ModuleId('identity.security_notifications'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(PrivilegedAccessConfiguration::class, self::ID, $this->configuration));
        $context->service(ServiceDefinition::factory(
            MySqlPrivilegedAccessSchemaVerifier::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlPrivilegedAccessSchemaVerifier =>
                new MySqlPrivilegedAccessSchemaVerifier(ServiceReference::get($r, DatabaseConnectionProvider::class))),
        ));
        $context->alias(PrivilegedAccessSchemaVerifier::class, MySqlPrivilegedAccessSchemaVerifier::class);
        $context->service(ServiceDefinition::factory(
            PrivilegedAccessReadinessCheck::class,
            self::ID,
            [PrivilegedAccessSchemaVerifier::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PrivilegedAccessReadinessCheck =>
                new PrivilegedAccessReadinessCheck(ServiceReference::get($r, PrivilegedAccessSchemaVerifier::class))),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlPrivilegedAccessAuthorizationRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlPrivilegedAccessAuthorizationRepository =>
                new MySqlPrivilegedAccessAuthorizationRepository(ServiceReference::get($r, DatabaseConnectionProvider::class))),
        ));
        $context->alias(PrivilegedAccessAuthorizationRepository::class, MySqlPrivilegedAccessAuthorizationRepository::class);
        $context->service(ServiceDefinition::factory(
            MySqlPrivilegedAccessContextRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlPrivilegedAccessContextRepository =>
                new MySqlPrivilegedAccessContextRepository(ServiceReference::get($r, DatabaseConnectionProvider::class))),
        ));
        $context->alias(PrivilegedAccessContextRepository::class, MySqlPrivilegedAccessContextRepository::class);
        $context->service(ServiceDefinition::factory(
            MySqlPrivilegedAccessHttpRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlPrivilegedAccessHttpRepository =>
                new MySqlPrivilegedAccessHttpRepository(ServiceReference::get($r, DatabaseConnectionProvider::class))),
        ));
        $context->alias(PrivilegedAccessHttpRepository::class, MySqlPrivilegedAccessHttpRepository::class);
        $context->service(ServiceDefinition::factory(
            PrivilegedAccessContextResolver::class,
            self::ID,
            [PrivilegedAccessContextRepository::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PrivilegedAccessContextResolver =>
                new PrivilegedAccessContextResolver(
                    ServiceReference::get($r, PrivilegedAccessContextRepository::class),
                    ServiceReference::get($r, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            PrivilegedAccessContextMiddleware::class,
            self::ID,
            [PrivilegedAccessContextResolver::class, SessionTenantContextRepository::class, TransactionManager::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PrivilegedAccessContextMiddleware =>
                new PrivilegedAccessContextMiddleware(
                    ServiceReference::get($r, PrivilegedAccessContextResolver::class),
                    ServiceReference::get($r, SessionTenantContextRepository::class),
                    ServiceReference::get($r, TransactionManager::class),
                    ServiceReference::get($r, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlPrivilegedAccessActivationRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlPrivilegedAccessActivationRepository =>
                new MySqlPrivilegedAccessActivationRepository(ServiceReference::get($r, DatabaseConnectionProvider::class))),
        ));
        $context->alias(PrivilegedAccessActivationRepository::class, MySqlPrivilegedAccessActivationRepository::class);
        $context->service(ServiceDefinition::factory(
            PrivilegedAccessActivationService::class,
            self::ID,
            [PrivilegedAccessActivationRepository::class, SessionTenantContextRepository::class,
                BaseRoleAuthorizationGuard::class, PrivilegedAccessLifecycleRepository::class, StepUpGuard::class, PrivilegedAccessConfiguration::class,
                PrivilegedAccessNotificationService::class, SecurityAuditEventAppender::class, TransactionManager::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PrivilegedAccessActivationService =>
                new PrivilegedAccessActivationService(
                    ServiceReference::get($r, PrivilegedAccessActivationRepository::class),
                    ServiceReference::get($r, SessionTenantContextRepository::class),
                    ServiceReference::get($r, BaseRoleAuthorizationGuard::class),
                    ServiceReference::get($r, PrivilegedAccessLifecycleRepository::class),
                    ServiceReference::get($r, StepUpGuard::class),
                    ServiceReference::get($r, PrivilegedAccessConfiguration::class),
                    ServiceReference::get($r, PrivilegedAccessNotificationService::class),
                    ServiceReference::get($r, SecurityAuditEventAppender::class),
                    ServiceReference::get($r, TransactionManager::class),
                    ServiceReference::get($r, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlPrivilegedAccessRequestRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlPrivilegedAccessRequestRepository =>
                new MySqlPrivilegedAccessRequestRepository(ServiceReference::get($r, DatabaseConnectionProvider::class))),
        ));
        $context->alias(PrivilegedAccessRequestRepository::class, MySqlPrivilegedAccessRequestRepository::class);
        $context->service(ServiceDefinition::factory(
            PrivilegedAccessNotificationService::class,
            self::ID,
            [MultiFactorNotificationTargetRepository::class, AccountSecurityNotificationRepository::class,
                SecurityNotificationDeduplicationKeyFactory::class, SecurityNotificationConfiguration::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PrivilegedAccessNotificationService =>
                new PrivilegedAccessNotificationService(
                    ServiceReference::get($r, MultiFactorNotificationTargetRepository::class),
                    ServiceReference::get($r, AccountSecurityNotificationRepository::class),
                    ServiceReference::get($r, SecurityNotificationDeduplicationKeyFactory::class),
                    ServiceReference::get($r, SecurityNotificationConfiguration::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlPrivilegedAccessLifecycleRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlPrivilegedAccessLifecycleRepository =>
                new MySqlPrivilegedAccessLifecycleRepository(ServiceReference::get($r, DatabaseConnectionProvider::class))),
        ));
        $context->alias(PrivilegedAccessLifecycleRepository::class, MySqlPrivilegedAccessLifecycleRepository::class);
        $context->service(ServiceDefinition::factory(
            PrivilegedAccessRequestService::class,
            self::ID,
            [BaseRoleAuthorizationGuard::class, PrivilegedAccessRequestRepository::class,
                PrivilegedAccessConfiguration::class, IdentityRateLimiter::class, IdentityFingerprintGenerator::class,
                PrivilegedAccessNotificationService::class, TransactionManager::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PrivilegedAccessRequestService =>
                new PrivilegedAccessRequestService(
                    ServiceReference::get($r, BaseRoleAuthorizationGuard::class),
                    ServiceReference::get($r, PrivilegedAccessRequestRepository::class),
                    ServiceReference::get($r, PrivilegedAccessConfiguration::class),
                    ServiceReference::get($r, IdentityRateLimiter::class),
                    ServiceReference::get($r, IdentityFingerprintGenerator::class),
                    ServiceReference::get($r, PrivilegedAccessNotificationService::class),
                    ServiceReference::get($r, TransactionManager::class),
                    ServiceReference::get($r, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            PrivilegedAccessApprovalService::class,
            self::ID,
            [BaseRoleAuthorizationGuard::class, PrivilegedAccessLifecycleRepository::class, StepUpGuard::class,
                PrivilegedAccessConfiguration::class, PrivilegedAccessNotificationService::class,
                TransactionManager::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PrivilegedAccessApprovalService =>
                new PrivilegedAccessApprovalService(
                    ServiceReference::get($r, BaseRoleAuthorizationGuard::class),
                    ServiceReference::get($r, PrivilegedAccessLifecycleRepository::class),
                    ServiceReference::get($r, StepUpGuard::class),
                    ServiceReference::get($r, PrivilegedAccessConfiguration::class),
                    ServiceReference::get($r, PrivilegedAccessNotificationService::class),
                    ServiceReference::get($r, TransactionManager::class),
                    ServiceReference::get($r, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            PrivilegedAccessRequestCancellationService::class,
            self::ID,
            [PrivilegedAccessLifecycleRepository::class, TransactionManager::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PrivilegedAccessRequestCancellationService =>
                new PrivilegedAccessRequestCancellationService(
                    ServiceReference::get($r, PrivilegedAccessLifecycleRepository::class),
                    ServiceReference::get($r, TransactionManager::class),
                    ServiceReference::get($r, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            PrivilegedAccessEndService::class,
            self::ID,
            [PrivilegedAccessLifecycleRepository::class, SessionTenantContextRepository::class,
                PrivilegedAccessNotificationService::class, SecurityAuditEventAppender::class, TransactionManager::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PrivilegedAccessEndService =>
                new PrivilegedAccessEndService(
                    ServiceReference::get($r, PrivilegedAccessLifecycleRepository::class),
                    ServiceReference::get($r, SessionTenantContextRepository::class),
                    ServiceReference::get($r, PrivilegedAccessNotificationService::class),
                    ServiceReference::get($r, SecurityAuditEventAppender::class),
                    ServiceReference::get($r, TransactionManager::class),
                    ServiceReference::get($r, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            PrivilegedAccessRevocationService::class,
            self::ID,
            [BaseRoleAuthorizationGuard::class, PrivilegedAccessLifecycleRepository::class, StepUpGuard::class,
                PrivilegedAccessNotificationService::class, SecurityAuditEventAppender::class, TransactionManager::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PrivilegedAccessRevocationService =>
                new PrivilegedAccessRevocationService(
                    ServiceReference::get($r, BaseRoleAuthorizationGuard::class),
                    ServiceReference::get($r, PrivilegedAccessLifecycleRepository::class),
                    ServiceReference::get($r, StepUpGuard::class),
                    ServiceReference::get($r, PrivilegedAccessNotificationService::class),
                    ServiceReference::get($r, SecurityAuditEventAppender::class),
                    ServiceReference::get($r, TransactionManager::class),
                    ServiceReference::get($r, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            PrivilegedAccessReviewService::class,
            self::ID,
            [BaseRoleAuthorizationGuard::class, PrivilegedAccessLifecycleRepository::class, StepUpGuard::class,
                PrivilegedAccessNotificationService::class, PrivilegedAccessConfiguration::class, SecurityAuditEventAppender::class,
                TransactionManager::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PrivilegedAccessReviewService =>
                new PrivilegedAccessReviewService(
                    ServiceReference::get($r, BaseRoleAuthorizationGuard::class),
                    ServiceReference::get($r, PrivilegedAccessLifecycleRepository::class),
                    ServiceReference::get($r, StepUpGuard::class),
                    ServiceReference::get($r, PrivilegedAccessNotificationService::class),
                    ServiceReference::get($r, PrivilegedAccessConfiguration::class),
                    ServiceReference::get($r, SecurityAuditEventAppender::class),
                    ServiceReference::get($r, TransactionManager::class),
                    ServiceReference::get($r, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            BreakGlassActivationService::class,
            self::ID,
            [BaseRoleAuthorizationGuard::class, PrivilegedAccessRequestRepository::class,
                PrivilegedAccessActivationRepository::class, PrivilegedAccessLifecycleRepository::class,
                SessionTenantContextRepository::class, StepUpGuard::class, IdentityRateLimiter::class,
                IdentityFingerprintGenerator::class, PrivilegedAccessNotificationService::class, SecurityAuditEventAppender::class,
                PrivilegedAccessConfiguration::class, TransactionManager::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): BreakGlassActivationService =>
                new BreakGlassActivationService(
                    ServiceReference::get($r, BaseRoleAuthorizationGuard::class),
                    ServiceReference::get($r, PrivilegedAccessRequestRepository::class),
                    ServiceReference::get($r, PrivilegedAccessActivationRepository::class),
                    ServiceReference::get($r, PrivilegedAccessLifecycleRepository::class),
                    ServiceReference::get($r, SessionTenantContextRepository::class),
                    ServiceReference::get($r, StepUpGuard::class),
                    ServiceReference::get($r, IdentityRateLimiter::class),
                    ServiceReference::get($r, IdentityFingerprintGenerator::class),
                    ServiceReference::get($r, PrivilegedAccessNotificationService::class),
                    ServiceReference::get($r, SecurityAuditEventAppender::class),
                    ServiceReference::get($r, PrivilegedAccessConfiguration::class),
                    ServiceReference::get($r, TransactionManager::class),
                    ServiceReference::get($r, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            PrivilegedAccessController::class,
            self::ID,
            [AuthenticatedRequestGuard::class, IdentityCsrf::class, IdentityAccessView::class, Psr17Factory::class,
                PrivilegedAccessHttpRepository::class, PrivilegedAccessLifecycleRepository::class,
                PrivilegedAccessRequestService::class, BreakGlassActivationService::class, PrivilegedAccessApprovalService::class,
                PrivilegedAccessRequestCancellationService::class, PrivilegedAccessActivationService::class,
                PrivilegedAccessRevocationService::class, PrivilegedAccessEndService::class, PrivilegedAccessReviewService::class,
                PrivilegedAccessConfiguration::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PrivilegedAccessController =>
                new PrivilegedAccessController(
                    ServiceReference::get($r, AuthenticatedRequestGuard::class),
                    ServiceReference::get($r, IdentityCsrf::class),
                    ServiceReference::get($r, IdentityAccessView::class),
                    ServiceReference::get($r, Psr17Factory::class),
                    ServiceReference::get($r, PrivilegedAccessHttpRepository::class),
                    ServiceReference::get($r, PrivilegedAccessLifecycleRepository::class),
                    ServiceReference::get($r, PrivilegedAccessRequestService::class),
                    ServiceReference::get($r, BreakGlassActivationService::class),
                    ServiceReference::get($r, PrivilegedAccessApprovalService::class),
                    ServiceReference::get($r, PrivilegedAccessRequestCancellationService::class),
                    ServiceReference::get($r, PrivilegedAccessActivationService::class),
                    ServiceReference::get($r, PrivilegedAccessRevocationService::class),
                    ServiceReference::get($r, PrivilegedAccessEndService::class),
                    ServiceReference::get($r, PrivilegedAccessReviewService::class),
                    ServiceReference::get($r, PrivilegedAccessConfiguration::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            PrivilegedAccessAuthorizationService::class,
            self::ID,
            [RoleBasedAuthorizationService::class, AuthorizationCatalog::class, AuthenticationAssuranceComparator::class,
                PrivilegedAccessAuthorizationRepository::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PrivilegedAccessAuthorizationService =>
                new PrivilegedAccessAuthorizationService(
                    ServiceReference::get($r, RoleBasedAuthorizationService::class),
                    ServiceReference::get($r, AuthorizationCatalog::class),
                    ServiceReference::get($r, AuthenticationAssuranceComparator::class),
                    ServiceReference::get($r, PrivilegedAccessAuthorizationRepository::class),
                    ServiceReference::get($r, Clock::class),
                )),
        ));
        $context->alias(AuthorizationService::class, PrivilegedAccessAuthorizationService::class);
        $context->service(ServiceDefinition::factory(
            AuthorizationGuard::class,
            self::ID,
            [AuthorizationService::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): AuthorizationGuard =>
                new AuthorizationGuard(ServiceReference::get($r, AuthorizationService::class))),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlPrivilegedAccessMaintenanceRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlPrivilegedAccessMaintenanceRepository =>
                new MySqlPrivilegedAccessMaintenanceRepository(ServiceReference::get($r, DatabaseConnectionProvider::class))),
        ));
        $context->alias(PrivilegedAccessMaintenanceRepository::class, MySqlPrivilegedAccessMaintenanceRepository::class);
        $context->service(ServiceDefinition::factory(
            PrivilegedAccessMaintenanceService::class,
            self::ID,
            [PrivilegedAccessMaintenanceRepository::class, PrivilegedAccessNotificationService::class,
                PrivilegedAccessConfiguration::class, SecurityAuditEventAppender::class,
                TransactionManager::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PrivilegedAccessMaintenanceService =>
                new PrivilegedAccessMaintenanceService(
                    ServiceReference::get($r, PrivilegedAccessMaintenanceRepository::class),
                    ServiceReference::get($r, PrivilegedAccessNotificationService::class),
                    ServiceReference::get($r, PrivilegedAccessConfiguration::class),
                    ServiceReference::get($r, SecurityAuditEventAppender::class),
                    ServiceReference::get($r, TransactionManager::class),
                    ServiceReference::get($r, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            ScheduledPrivilegedAccessMaintenanceTask::class,
            self::ID,
            [PrivilegedAccessMaintenanceService::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): ScheduledPrivilegedAccessMaintenanceTask =>
                new ScheduledPrivilegedAccessMaintenanceTask(ServiceReference::get($r, PrivilegedAccessMaintenanceService::class))),
        ));
        $context->service(ServiceDefinition::factory(
            PrivilegedAccessVerifyConsoleCommand::class,
            self::ID,
            [PrivilegedAccessSchemaVerifier::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PrivilegedAccessVerifyConsoleCommand =>
                new PrivilegedAccessVerifyConsoleCommand(ServiceReference::get($r, PrivilegedAccessSchemaVerifier::class))),
        ));
        $context->scheduledTask(new ScheduledTaskRegistration(
            new ScheduledTaskId('security.privileged_access.maintain'),
            'Expire controlled privileged access and mark required reviews overdue.',
            new FixedIntervalSchedule(60),
            ScheduledPrivilegedAccessMaintenanceTask::class,
            120,
            self::ID,
        ));
    }
}
