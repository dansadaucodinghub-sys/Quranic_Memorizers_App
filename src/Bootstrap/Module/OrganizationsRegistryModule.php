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
use Qmdb\Modules\Organizations\Application\OrganizationRegistryRepository;
use Qmdb\Modules\Organizations\Application\OrganizationsRegistryReadinessCheck;
use Qmdb\Modules\Organizations\Application\OrganizationsRegistryService;
use Qmdb\Modules\Organizations\Configuration\OrganizationsRegistryConfiguration;
use Qmdb\Modules\Organizations\Domain\OrganizationAccessPolicy;
use Qmdb\Modules\Organizations\Domain\OrganizationRegistryCodeGenerator;
use Qmdb\Modules\Organizations\Infrastructure\Persistence\MySqlOrganizationRegistryRepository;
use Qmdb\Modules\Organizations\Infrastructure\Security\SecureOrganizationRegistryCodeGenerator;
use Qmdb\Modules\Organizations\Interface\Console\OrganizationsRegistryVerifyConsoleCommand;
use Qmdb\Modules\Organizations\Interface\Http\OrganizationsRegistryController;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditRecorder;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
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

final readonly class OrganizationsRegistryModule implements Module
{
    private const string ID = 'organizations.registry';
    public function __construct(private OrganizationsRegistryConfiguration $configuration)
    {
    }

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [new ModuleId('foundation.core'),new ModuleId('foundation.application'),new ModuleId('foundation.observability'),new ModuleId('foundation.database'),new ModuleId('foundation.schema'),new ModuleId('foundation.http'),new ModuleId('foundation.presentation'),new ModuleId('security.web'),new ModuleId('security.audit'),new ModuleId('security.authorization'),new ModuleId('identity.accounts'),new ModuleId('identity.access'),new ModuleId('identity.sessions'),new ModuleId('identity.multifactor'),new ModuleId('tenancy.context'),new ModuleId('reference.geography')];
    }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(OrganizationsRegistryConfiguration::class, self::ID, $this->configuration));
        $context->service(ServiceDefinition::instance(OrganizationAccessPolicy::class, self::ID, new OrganizationAccessPolicy()));
        $context->service(ServiceDefinition::instance(OrganizationRegistryCodeGenerator::class, self::ID, new SecureOrganizationRegistryCodeGenerator()));
        $context->service(ServiceDefinition::factory(MySqlOrganizationRegistryRepository::class, self::ID, [DatabaseConnectionProvider::class,OrganizationsRegistryConfiguration::class], new ClosureServiceFactory(static fn(DependencyResolver $r): MySqlOrganizationRegistryRepository=>new MySqlOrganizationRegistryRepository(ServiceReference::get($r, DatabaseConnectionProvider::class), ServiceReference::get($r, OrganizationsRegistryConfiguration::class)))));
        $context->alias(OrganizationRegistryRepository::class, MySqlOrganizationRegistryRepository::class);
        $context->service(ServiceDefinition::factory(OrganizationsRegistryService::class, self::ID, [OrganizationRegistryRepository::class,IdentityAccessRepository::class,IdentityRateLimiter::class,IdentityFingerprintGenerator::class,AuthorizationRequirementGuard::class,StepUpGuard::class,SecurityAuditRecorder::class,OrganizationRegistryCodeGenerator::class,OrganizationsRegistryConfiguration::class,TransactionManager::class,Clock::class], new ClosureServiceFactory(static fn(DependencyResolver $r): OrganizationsRegistryService=>new OrganizationsRegistryService(ServiceReference::get($r, OrganizationRegistryRepository::class), ServiceReference::get($r, IdentityAccessRepository::class), ServiceReference::get($r, IdentityRateLimiter::class), ServiceReference::get($r, IdentityFingerprintGenerator::class), ServiceReference::get($r, AuthorizationRequirementGuard::class), ServiceReference::get($r, StepUpGuard::class), ServiceReference::get($r, SecurityAuditRecorder::class), ServiceReference::get($r, OrganizationRegistryCodeGenerator::class), ServiceReference::get($r, OrganizationsRegistryConfiguration::class), ServiceReference::get($r, TransactionManager::class), ServiceReference::get($r, Clock::class)))));
        $context->service(ServiceDefinition::factory(OrganizationsRegistryReadinessCheck::class, self::ID, [OrganizationsRegistryConfiguration::class,SchemaHealthCheck::class,OrganizationRegistryRepository::class], new ClosureServiceFactory(static fn(DependencyResolver $r): OrganizationsRegistryReadinessCheck=>new OrganizationsRegistryReadinessCheck(ServiceReference::get($r, OrganizationsRegistryConfiguration::class), ServiceReference::get($r, SchemaHealthCheck::class), ServiceReference::get($r, OrganizationRegistryRepository::class)))));
        $context->service(ServiceDefinition::factory(OrganizationsRegistryVerifyConsoleCommand::class, self::ID, [OrganizationsRegistryConfiguration::class,OrganizationsRegistryReadinessCheck::class], new ClosureServiceFactory(static fn(DependencyResolver $r): OrganizationsRegistryVerifyConsoleCommand=>new OrganizationsRegistryVerifyConsoleCommand(ServiceReference::get($r, OrganizationsRegistryConfiguration::class), ServiceReference::get($r, OrganizationsRegistryReadinessCheck::class)))));
        $context->service(ServiceDefinition::factory(OrganizationsRegistryController::class, self::ID, [AuthenticatedRequestGuard::class,TenantContextRequiredGuard::class,IdentityCsrf::class,IdentityAccessView::class,OrganizationsRegistryService::class,OrganizationsRegistryConfiguration::class], new ClosureServiceFactory(static fn(DependencyResolver $r): OrganizationsRegistryController=>new OrganizationsRegistryController(ServiceReference::get($r, AuthenticatedRequestGuard::class), ServiceReference::get($r, TenantContextRequiredGuard::class), ServiceReference::get($r, IdentityCsrf::class), ServiceReference::get($r, IdentityAccessView::class), ServiceReference::get($r, OrganizationsRegistryService::class), ServiceReference::get($r, OrganizationsRegistryConfiguration::class)))));
    }
}
