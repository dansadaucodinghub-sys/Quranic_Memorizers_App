<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccountState\Application\AccountStateOperationService;
use Qmdb\Modules\IdentityAccountState\Application\AccountStateRepository;
use Qmdb\Modules\IdentityAccountState\Application\AccountStateSecurityNotificationService;
use Qmdb\Modules\IdentityAccountState\Configuration\AccountStateConfiguration;
use Qmdb\Modules\IdentityAccountState\Infrastructure\Persistence\MySqlAccountStateRepository;
use Qmdb\Modules\IdentityAccountState\Infrastructure\Persistence\MySqlAccountStateHttpRepository;
use Qmdb\Modules\IdentityAccountState\Application\AccountStateHttpRepository;
use Qmdb\Modules\IdentityAccountState\Interface\Http\AccountStateSecurityController;
use Qmdb\Modules\IdentityAccountState\Interface\Http\SecurityAuditViewerController;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\MultiFactorNotificationTargetRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditRecorder;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditRepository;
use Qmdb\Modules\SecurityAuthorization\Application\BaseRoleAuthorizationGuard;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Nyholm\Psr7\Factory\Psr17Factory;
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

final readonly class IdentityAccountStateModule implements Module
{
    private const string ID = 'identity.account_state';

    public function __construct(private AccountStateConfiguration $configuration)
    {
    }

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('foundation.core'), new ModuleId('foundation.application'), new ModuleId('foundation.observability'),
            new ModuleId('foundation.database'), new ModuleId('foundation.schema'), new ModuleId('foundation.http'),
            new ModuleId('foundation.presentation'), new ModuleId('security.web'), new ModuleId('identity.accounts'),
            new ModuleId('identity.access'), new ModuleId('identity.sessions'), new ModuleId('identity.recovery'),
            new ModuleId('identity.multifactor'), new ModuleId('identity.security_notifications'),
            new ModuleId('security.authorization'), new ModuleId('security.privileged_access'), new ModuleId('security.audit'),
            new ModuleId('tenancy.context'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(AccountStateConfiguration::class, self::ID, $this->configuration));
        $context->service(ServiceDefinition::factory(
            MySqlAccountStateRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlAccountStateRepository => new MySqlAccountStateRepository(
                ServiceReference::get($r, DatabaseConnectionProvider::class),
            )),
        ));
        $context->alias(AccountStateRepository::class, MySqlAccountStateRepository::class);
        $context->service(ServiceDefinition::factory(
            MySqlAccountStateHttpRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlAccountStateHttpRepository => new MySqlAccountStateHttpRepository(
                ServiceReference::get($r, DatabaseConnectionProvider::class),
            )),
        ));
        $context->alias(AccountStateHttpRepository::class, MySqlAccountStateHttpRepository::class);
        $context->service(ServiceDefinition::factory(
            AccountStateSecurityNotificationService::class,
            self::ID,
            [MultiFactorNotificationTargetRepository::class, AccountSecurityNotificationRepository::class,
                SecurityNotificationDeduplicationKeyFactory::class, SecurityNotificationConfiguration::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): AccountStateSecurityNotificationService =>
                new AccountStateSecurityNotificationService(
                    ServiceReference::get($r, MultiFactorNotificationTargetRepository::class),
                    ServiceReference::get($r, AccountSecurityNotificationRepository::class),
                    ServiceReference::get($r, SecurityNotificationDeduplicationKeyFactory::class),
                    ServiceReference::get($r, SecurityNotificationConfiguration::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            AccountStateOperationService::class,
            self::ID,
            [BaseRoleAuthorizationGuard::class, StepUpGuard::class, IdentityRateLimiter::class,
                IdentityFingerprintGenerator::class, AccountStateRepository::class, SecurityAuditRecorder::class,
                AccountStateSecurityNotificationService::class, AccountStateConfiguration::class,
                TransactionManager::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): AccountStateOperationService => new AccountStateOperationService(
                ServiceReference::get($r, BaseRoleAuthorizationGuard::class),
                ServiceReference::get($r, StepUpGuard::class),
                ServiceReference::get($r, IdentityRateLimiter::class),
                ServiceReference::get($r, IdentityFingerprintGenerator::class),
                ServiceReference::get($r, AccountStateRepository::class),
                ServiceReference::get($r, SecurityAuditRecorder::class),
                ServiceReference::get($r, AccountStateSecurityNotificationService::class),
                ServiceReference::get($r, AccountStateConfiguration::class),
                ServiceReference::get($r, TransactionManager::class),
                ServiceReference::get($r, Clock::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            AccountStateSecurityController::class,
            self::ID,
            [AuthenticatedRequestGuard::class, BaseRoleAuthorizationGuard::class, IdentityCsrf::class,
                IdentityAccessView::class, Psr17Factory::class, AccountStateHttpRepository::class,
                AccountStateOperationService::class, SecurityAuditRepository::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): AccountStateSecurityController => new AccountStateSecurityController(
                ServiceReference::get($r, AuthenticatedRequestGuard::class),
                ServiceReference::get($r, BaseRoleAuthorizationGuard::class),
                ServiceReference::get($r, IdentityCsrf::class),
                ServiceReference::get($r, IdentityAccessView::class),
                ServiceReference::get($r, Psr17Factory::class),
                ServiceReference::get($r, AccountStateHttpRepository::class),
                ServiceReference::get($r, AccountStateOperationService::class),
                ServiceReference::get($r, SecurityAuditRepository::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            SecurityAuditViewerController::class,
            self::ID,
            [AuthenticatedRequestGuard::class, BaseRoleAuthorizationGuard::class, SecurityAuditRepository::class,
                IdentityAccessView::class, Psr17Factory::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): SecurityAuditViewerController => new SecurityAuditViewerController(
                ServiceReference::get($r, AuthenticatedRequestGuard::class),
                ServiceReference::get($r, BaseRoleAuthorizationGuard::class),
                ServiceReference::get($r, SecurityAuditRepository::class),
                ServiceReference::get($r, IdentityAccessView::class),
                ServiceReference::get($r, Psr17Factory::class),
            )),
        ));
    }
}
