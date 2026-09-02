<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;
use Qmdb\Modules\OrganizationAffiliations\Application\OrganizationAffiliationRepository;
use Qmdb\Modules\OrganizationAffiliations\Application\OrganizationAffiliationPersonCanonicalizationParticipant;
use Qmdb\Modules\OrganizationAffiliations\Application\OrganizationAffiliationService;
use Qmdb\Modules\OrganizationAffiliations\Application\OrganizationAffiliationsReadinessCheck;
use Qmdb\Modules\OrganizationAffiliations\Application\ScheduledOrganizationAffiliationMaintenanceTask;
use Qmdb\Modules\OrganizationAffiliations\Configuration\OrganizationAffiliationsConfiguration;
use Qmdb\Modules\OrganizationAffiliations\Domain\OrganizationAffiliationCodeGenerator;
use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Persistence\MySqlOrganizationAffiliationRepository;
use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Persistence\MySqlOrganizationAffiliationCanonicalizationParticipant;
use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Security\SecureOrganizationAffiliationCodeGenerator;
use Qmdb\Modules\OrganizationAffiliations\Interface\Console\OrganizationAffiliationsVerifyConsoleCommand;
use Qmdb\Modules\OrganizationAffiliations\Interface\Http\OrganizationAffiliationsController;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Background\Scheduler\FixedIntervalSchedule;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskId;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRegistration;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Qmdb\Shared\Time\Clock;

final readonly class OrganizationsAffiliationsModule implements Module
{
    private const string ID = 'organizations.affiliations';
    public function __construct(private OrganizationAffiliationsConfiguration $configuration)
    {
    }
    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }
    public function dependencies(): array
    {
        return [new ModuleId('foundation.core'),new ModuleId('foundation.application'),new ModuleId('foundation.database'),new ModuleId('foundation.schema'),new ModuleId('foundation.background'),new ModuleId('foundation.http'),new ModuleId('foundation.presentation'),new ModuleId('security.authorization'),new ModuleId('security.audit'),new ModuleId('security.web'),new ModuleId('identity.access'),new ModuleId('identity.sessions'),new ModuleId('identity.multifactor'),new ModuleId('identity.security_notifications'),new ModuleId('tenancy.context'),new ModuleId('people.profiles'),new ModuleId('organizations.registry')];
    }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(OrganizationAffiliationsConfiguration::class, self::ID, $this->configuration));
        $context->service(ServiceDefinition::instance(OrganizationAffiliationCodeGenerator::class, self::ID, new SecureOrganizationAffiliationCodeGenerator()));
        $context->service(ServiceDefinition::factory(MySqlOrganizationAffiliationRepository::class, self::ID, [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn(DependencyResolver $r): MySqlOrganizationAffiliationRepository=>new MySqlOrganizationAffiliationRepository(ServiceReference::get($r, DatabaseConnectionProvider::class)))));
        $context->alias(OrganizationAffiliationRepository::class, MySqlOrganizationAffiliationRepository::class);
        $context->service(ServiceDefinition::factory(MySqlOrganizationAffiliationCanonicalizationParticipant::class, self::ID, [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn(DependencyResolver $r): MySqlOrganizationAffiliationCanonicalizationParticipant => new MySqlOrganizationAffiliationCanonicalizationParticipant(ServiceReference::get($r, DatabaseConnectionProvider::class)))));
        $context->alias(OrganizationAffiliationPersonCanonicalizationParticipant::class, MySqlOrganizationAffiliationCanonicalizationParticipant::class);
        $context->service(ServiceDefinition::factory(OrganizationAffiliationService::class, self::ID, [OrganizationAffiliationRepository::class,IdentityAccessRepository::class,IdentityRateLimiter::class,IdentityFingerprintGenerator::class,AuthorizationRequirementGuard::class,StepUpGuard::class,SecurityAuditEventAppender::class,AccountSecurityNotificationRepository::class,SecurityNotificationDeduplicationKeyFactory::class,OrganizationAffiliationCodeGenerator::class,OrganizationAffiliationsConfiguration::class,TransactionManager::class,Clock::class], new ClosureServiceFactory(static fn(DependencyResolver $r): OrganizationAffiliationService=>new OrganizationAffiliationService(ServiceReference::get($r, OrganizationAffiliationRepository::class), ServiceReference::get($r, IdentityAccessRepository::class), ServiceReference::get($r, IdentityRateLimiter::class), ServiceReference::get($r, IdentityFingerprintGenerator::class), ServiceReference::get($r, AuthorizationRequirementGuard::class), ServiceReference::get($r, StepUpGuard::class), ServiceReference::get($r, SecurityAuditEventAppender::class), ServiceReference::get($r, AccountSecurityNotificationRepository::class), ServiceReference::get($r, SecurityNotificationDeduplicationKeyFactory::class), ServiceReference::get($r, OrganizationAffiliationCodeGenerator::class), ServiceReference::get($r, OrganizationAffiliationsConfiguration::class), ServiceReference::get($r, TransactionManager::class), ServiceReference::get($r, Clock::class)))));
        $context->service(ServiceDefinition::factory(OrganizationAffiliationsReadinessCheck::class, self::ID, [OrganizationAffiliationsConfiguration::class,SchemaHealthCheck::class,OrganizationAffiliationRepository::class], new ClosureServiceFactory(static fn(DependencyResolver $r): OrganizationAffiliationsReadinessCheck=>new OrganizationAffiliationsReadinessCheck(ServiceReference::get($r, OrganizationAffiliationsConfiguration::class), ServiceReference::get($r, SchemaHealthCheck::class), ServiceReference::get($r, OrganizationAffiliationRepository::class)))));
        $context->service(ServiceDefinition::factory(OrganizationAffiliationsVerifyConsoleCommand::class, self::ID, [OrganizationAffiliationsReadinessCheck::class], new ClosureServiceFactory(static fn(DependencyResolver $r): OrganizationAffiliationsVerifyConsoleCommand=>new OrganizationAffiliationsVerifyConsoleCommand(ServiceReference::get($r, OrganizationAffiliationsReadinessCheck::class)))));
        $context->service(ServiceDefinition::factory(OrganizationAffiliationsController::class, self::ID, [AuthenticatedRequestGuard::class,TenantContextRequiredGuard::class,IdentityCsrf::class,IdentityAccessView::class,OrganizationAffiliationService::class,OrganizationAffiliationsConfiguration::class], new ClosureServiceFactory(static fn(DependencyResolver $r): OrganizationAffiliationsController=>new OrganizationAffiliationsController(ServiceReference::get($r, AuthenticatedRequestGuard::class), ServiceReference::get($r, TenantContextRequiredGuard::class), ServiceReference::get($r, IdentityCsrf::class), ServiceReference::get($r, IdentityAccessView::class), ServiceReference::get($r, OrganizationAffiliationService::class), ServiceReference::get($r, OrganizationAffiliationsConfiguration::class)))));
        $context->service(ServiceDefinition::factory(ScheduledOrganizationAffiliationMaintenanceTask::class, self::ID, [OrganizationAffiliationService::class], new ClosureServiceFactory(static fn(DependencyResolver $r): ScheduledOrganizationAffiliationMaintenanceTask=>new ScheduledOrganizationAffiliationMaintenanceTask(ServiceReference::get($r, OrganizationAffiliationService::class)))));
        $context->scheduledTask(new ScheduledTaskRegistration(new ScheduledTaskId('organizations.affiliations.maintain'), 'Expire pending private Organization affiliation requests.', new FixedIntervalSchedule(900), ScheduledOrganizationAffiliationMaintenanceTask::class, 120, self::ID));
    }
}
