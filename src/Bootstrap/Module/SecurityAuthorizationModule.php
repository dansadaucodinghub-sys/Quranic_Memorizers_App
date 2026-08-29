<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Closure;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\MultiFactorNotificationTargetRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;
use Qmdb\Modules\SecurityAuthorization\Application\AuthenticationAssuranceComparator;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationCatalogVerifier;
use Qmdb\Modules\SecurityAuthorization\Application\BaseRoleAuthorizationGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationReadinessCheck;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSecurityNotificationService;
use Qmdb\Modules\SecurityAuthorization\Application\DelegationValidator;
use Qmdb\Modules\SecurityAuthorization\Application\PlatformRoleAssignmentService;
use Qmdb\Modules\SecurityAuthorization\Application\PlatformRoleRevocationService;
use Qmdb\Modules\SecurityAuthorization\Application\RoleBasedAuthorizationService;
use Qmdb\Modules\SecurityAuthorization\Application\WorkspaceRoleAssignmentService;
use Qmdb\Modules\SecurityAuthorization\Application\WorkspaceRoleRevocationService;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalog;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\AuthorizationAdministrationRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\AuthorizationCatalogVerificationRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\EffectivePermissionRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\PlatformRoleAssignmentRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\WorkspaceRoleAssignmentRepository;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence\MySqlAuthorizationAdministrationRepository;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence\MySqlAuthorizationCatalogVerificationRepository;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence\MySqlEffectivePermissionRepository;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence\MySqlPlatformRoleAssignmentRepository;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence\MySqlWorkspaceRoleAssignmentRepository;
use Qmdb\Modules\SecurityAuthorization\Interface\Console\AuthorizationVerifyConsoleCommand;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Time\Clock;

final readonly class SecurityAuthorizationModule implements Module
{
    private const string ID = 'security.authorization';

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('foundation.core'),
            new ModuleId('foundation.application'),
            new ModuleId('foundation.observability'),
            new ModuleId('foundation.database'),
            new ModuleId('foundation.schema'),
            new ModuleId('foundation.http'),
            new ModuleId('identity.accounts'),
            new ModuleId('identity.sessions'),
            new ModuleId('identity.multifactor'),
            new ModuleId('identity.security_notifications'),
            new ModuleId('tenancy.workspaces'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(
            AuthorizationCatalog::class,
            self::ID,
            AuthorizationCatalogRegistry::withPrivilegedAccess(),
        ));
        $context->service(ServiceDefinition::instance(
            AuthenticationAssuranceComparator::class,
            self::ID,
            new AuthenticationAssuranceComparator(),
        ));
        $this->persistence($context);
        $this->authorization($context);
        $this->administration($context);
        $this->verification($context);
    }

    private function persistence(ModuleRegistrationContext $context): void
    {
        $this->factory(
            $context,
            MySqlEffectivePermissionRepository::class,
            [DatabaseConnectionProvider::class],
            static fn (DependencyResolver $r): MySqlEffectivePermissionRepository =>
            new MySqlEffectivePermissionRepository(ServiceReference::get($r, DatabaseConnectionProvider::class))
        );
        $context->alias(EffectivePermissionRepository::class, MySqlEffectivePermissionRepository::class);

        $this->factory(
            $context,
            MySqlAuthorizationAdministrationRepository::class,
            [DatabaseConnectionProvider::class],
            static fn (DependencyResolver $r): MySqlAuthorizationAdministrationRepository =>
                new MySqlAuthorizationAdministrationRepository(
                    ServiceReference::get($r, DatabaseConnectionProvider::class),
                )
        );
        $context->alias(
            AuthorizationAdministrationRepository::class,
            MySqlAuthorizationAdministrationRepository::class,
        );

        $this->factory(
            $context,
            MySqlPlatformRoleAssignmentRepository::class,
            [DatabaseConnectionProvider::class],
            static fn (DependencyResolver $r): MySqlPlatformRoleAssignmentRepository =>
                new MySqlPlatformRoleAssignmentRepository(
                    ServiceReference::get($r, DatabaseConnectionProvider::class),
                )
        );
        $context->alias(PlatformRoleAssignmentRepository::class, MySqlPlatformRoleAssignmentRepository::class);

        $this->factory(
            $context,
            MySqlWorkspaceRoleAssignmentRepository::class,
            [DatabaseConnectionProvider::class],
            static fn (DependencyResolver $r): MySqlWorkspaceRoleAssignmentRepository =>
                new MySqlWorkspaceRoleAssignmentRepository(
                    ServiceReference::get($r, DatabaseConnectionProvider::class),
                )
        );
        $context->alias(WorkspaceRoleAssignmentRepository::class, MySqlWorkspaceRoleAssignmentRepository::class);

        $this->factory(
            $context,
            MySqlAuthorizationCatalogVerificationRepository::class,
            [DatabaseConnectionProvider::class],
            static fn (DependencyResolver $r): MySqlAuthorizationCatalogVerificationRepository =>
                new MySqlAuthorizationCatalogVerificationRepository(
                    ServiceReference::get($r, DatabaseConnectionProvider::class),
                )
        );
        $context->alias(
            AuthorizationCatalogVerificationRepository::class,
            MySqlAuthorizationCatalogVerificationRepository::class,
        );
    }

    private function authorization(ModuleRegistrationContext $context): void
    {
        $this->factory($context, RoleBasedAuthorizationService::class, [
            AuthorizationCatalog::class,
            EffectivePermissionRepository::class,
            AuthenticationAssuranceComparator::class,
            EventLogger::class,
        ], static fn (DependencyResolver $r): RoleBasedAuthorizationService => new RoleBasedAuthorizationService(
            ServiceReference::get($r, AuthorizationCatalog::class),
            ServiceReference::get($r, EffectivePermissionRepository::class),
            ServiceReference::get($r, AuthenticationAssuranceComparator::class),
            ServiceReference::get($r, EventLogger::class),
        ));
        $this->factory(
            $context,
            BaseRoleAuthorizationGuard::class,
            [RoleBasedAuthorizationService::class],
            static fn (DependencyResolver $r): BaseRoleAuthorizationGuard => new BaseRoleAuthorizationGuard(
                ServiceReference::get($r, RoleBasedAuthorizationService::class),
            )
        );
        $this->factory($context, DelegationValidator::class, [
            EffectivePermissionRepository::class,
            AuthorizationAdministrationRepository::class,
        ], static fn (DependencyResolver $r): DelegationValidator => new DelegationValidator(
            ServiceReference::get($r, EffectivePermissionRepository::class),
            ServiceReference::get($r, AuthorizationAdministrationRepository::class),
        ));
    }

    private function administration(ModuleRegistrationContext $context): void
    {
        $this->factory($context, AuthorizationSecurityNotificationService::class, [
            MultiFactorNotificationTargetRepository::class,
            AccountSecurityNotificationRepository::class,
            SecurityNotificationDeduplicationKeyFactory::class,
            SecurityNotificationConfiguration::class,
        ], static fn (DependencyResolver $r): AuthorizationSecurityNotificationService =>
            new AuthorizationSecurityNotificationService(
                ServiceReference::get($r, MultiFactorNotificationTargetRepository::class),
                ServiceReference::get($r, AccountSecurityNotificationRepository::class),
                ServiceReference::get($r, SecurityNotificationDeduplicationKeyFactory::class),
                ServiceReference::get($r, SecurityNotificationConfiguration::class),
            ));
        $shared = [
            BaseRoleAuthorizationGuard::class,
            AuthorizationAdministrationRepository::class,
            DelegationValidator::class,
            StepUpGuard::class,
            AuthorizationSecurityNotificationService::class,
            TransactionManager::class,
            EventLogger::class,
            Clock::class,
        ];
        $this->factory($context, PlatformRoleAssignmentService::class, [
            ...$shared,
            PlatformRoleAssignmentRepository::class,
        ], static fn (DependencyResolver $r): PlatformRoleAssignmentService => new PlatformRoleAssignmentService(
            ServiceReference::get($r, BaseRoleAuthorizationGuard::class),
            ServiceReference::get($r, AuthorizationAdministrationRepository::class),
            ServiceReference::get($r, PlatformRoleAssignmentRepository::class),
            ServiceReference::get($r, DelegationValidator::class),
            ServiceReference::get($r, StepUpGuard::class),
            ServiceReference::get($r, AuthorizationSecurityNotificationService::class),
            ServiceReference::get($r, TransactionManager::class),
            ServiceReference::get($r, EventLogger::class),
            ServiceReference::get($r, Clock::class),
        ));
        $this->factory($context, PlatformRoleRevocationService::class, [
            ...$shared,
            PlatformRoleAssignmentRepository::class,
        ], static fn (DependencyResolver $r): PlatformRoleRevocationService => new PlatformRoleRevocationService(
            ServiceReference::get($r, BaseRoleAuthorizationGuard::class),
            ServiceReference::get($r, AuthorizationAdministrationRepository::class),
            ServiceReference::get($r, PlatformRoleAssignmentRepository::class),
            ServiceReference::get($r, DelegationValidator::class),
            ServiceReference::get($r, StepUpGuard::class),
            ServiceReference::get($r, AuthorizationSecurityNotificationService::class),
            ServiceReference::get($r, TransactionManager::class),
            ServiceReference::get($r, EventLogger::class),
            ServiceReference::get($r, Clock::class),
        ));
        $this->factory($context, WorkspaceRoleAssignmentService::class, [
            ...$shared,
            WorkspaceRoleAssignmentRepository::class,
        ], static fn (DependencyResolver $r): WorkspaceRoleAssignmentService => new WorkspaceRoleAssignmentService(
            ServiceReference::get($r, BaseRoleAuthorizationGuard::class),
            ServiceReference::get($r, AuthorizationAdministrationRepository::class),
            ServiceReference::get($r, WorkspaceRoleAssignmentRepository::class),
            ServiceReference::get($r, DelegationValidator::class),
            ServiceReference::get($r, StepUpGuard::class),
            ServiceReference::get($r, AuthorizationSecurityNotificationService::class),
            ServiceReference::get($r, TransactionManager::class),
            ServiceReference::get($r, EventLogger::class),
            ServiceReference::get($r, Clock::class),
        ));
        $this->factory($context, WorkspaceRoleRevocationService::class, [
            ...$shared,
            WorkspaceRoleAssignmentRepository::class,
        ], static fn (DependencyResolver $r): WorkspaceRoleRevocationService => new WorkspaceRoleRevocationService(
            ServiceReference::get($r, BaseRoleAuthorizationGuard::class),
            ServiceReference::get($r, AuthorizationAdministrationRepository::class),
            ServiceReference::get($r, WorkspaceRoleAssignmentRepository::class),
            ServiceReference::get($r, DelegationValidator::class),
            ServiceReference::get($r, StepUpGuard::class),
            ServiceReference::get($r, AuthorizationSecurityNotificationService::class),
            ServiceReference::get($r, TransactionManager::class),
            ServiceReference::get($r, EventLogger::class),
            ServiceReference::get($r, Clock::class),
        ));
    }

    private function verification(ModuleRegistrationContext $context): void
    {
        $this->factory($context, AuthorizationCatalogVerifier::class, [
            AuthorizationCatalog::class,
            AuthorizationCatalogVerificationRepository::class,
            EventLogger::class,
        ], static fn (DependencyResolver $r): AuthorizationCatalogVerifier => new AuthorizationCatalogVerifier(
            ServiceReference::get($r, AuthorizationCatalog::class),
            ServiceReference::get($r, AuthorizationCatalogVerificationRepository::class),
            ServiceReference::get($r, EventLogger::class),
        ));
        $this->factory(
            $context,
            AuthorizationReadinessCheck::class,
            [AuthorizationCatalogVerifier::class],
            static fn (DependencyResolver $r): AuthorizationReadinessCheck => new AuthorizationReadinessCheck(
                ServiceReference::get($r, AuthorizationCatalogVerifier::class),
            )
        );
        $this->factory(
            $context,
            AuthorizationVerifyConsoleCommand::class,
            [AuthorizationCatalogVerifier::class],
            static fn (DependencyResolver $r): AuthorizationVerifyConsoleCommand =>
            new AuthorizationVerifyConsoleCommand(ServiceReference::get($r, AuthorizationCatalogVerifier::class))
        );
    }

    /**
     * @param class-string $service
     * @param list<class-string> $dependencies
     * @param Closure(DependencyResolver): object $factory
     */
    private function factory(
        ModuleRegistrationContext $context,
        string $service,
        array $dependencies,
        Closure $factory,
    ): void {
        $context->service(ServiceDefinition::factory(
            $service,
            self::ID,
            $dependencies,
            new ClosureServiceFactory($factory),
        ));
    }
}
