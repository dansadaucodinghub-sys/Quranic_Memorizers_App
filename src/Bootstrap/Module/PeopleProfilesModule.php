<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\People\Application\PeopleProfileSecurityNotificationService;
use Qmdb\Modules\People\Application\PeopleProfilesReadinessCheck;
use Qmdb\Modules\People\Application\PeopleProfilesVerificationProbe;
use Qmdb\Modules\People\Application\PersonProfileRepository;
use Qmdb\Modules\People\Application\PersonProfileService;
use Qmdb\Modules\People\Configuration\PeopleProfilesConfiguration;
use Qmdb\Modules\People\Domain\PersonRegistryCodeGenerator;
use Qmdb\Modules\People\Domain\PersonProfileAccessPolicy;
use Qmdb\Modules\People\Infrastructure\Persistence\MySqlPersonProfileRepository;
use Qmdb\Modules\People\Infrastructure\Persistence\MySqlPeopleProfilesVerificationProbe;
use Qmdb\Modules\People\Infrastructure\Security\SecurePersonRegistryCodeGenerator;
use Qmdb\Modules\People\Interface\Console\PeopleProfilesVerifyConsoleCommand;
use Qmdb\Modules\People\Interface\Http\PeopleProfilesController;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\MultiFactorNotificationTargetRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\Geography\Application\NigeriaGeographyDirectoryHandler;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditRecorder;
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

final readonly class PeopleProfilesModule implements Module
{
    private const string ID = 'people.profiles';

    public function __construct(private PeopleProfilesConfiguration $configuration)
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
            new ModuleId('foundation.presentation'), new ModuleId('security.web'), new ModuleId('security.audit'),
            new ModuleId('identity.accounts'), new ModuleId('identity.access'), new ModuleId('identity.sessions'), new ModuleId('identity.multifactor'),
            new ModuleId('identity.security_notifications'), new ModuleId('reference.geography'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(PeopleProfilesConfiguration::class, self::ID, $this->configuration));
        $context->service(ServiceDefinition::instance(PersonProfileAccessPolicy::class, self::ID, new PersonProfileAccessPolicy()));
        $context->service(ServiceDefinition::instance(PersonRegistryCodeGenerator::class, self::ID, new SecurePersonRegistryCodeGenerator()));
        $context->service(ServiceDefinition::factory(
            MySqlPersonProfileRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlPersonProfileRepository => new MySqlPersonProfileRepository(ServiceReference::get($r, DatabaseConnectionProvider::class))),
        ));
        $context->alias(PersonProfileRepository::class, MySqlPersonProfileRepository::class);
        $context->service(ServiceDefinition::factory(
            MySqlPeopleProfilesVerificationProbe::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlPeopleProfilesVerificationProbe => new MySqlPeopleProfilesVerificationProbe(ServiceReference::get($r, DatabaseConnectionProvider::class))),
        ));
        $context->alias(PeopleProfilesVerificationProbe::class, MySqlPeopleProfilesVerificationProbe::class);
        $context->service(ServiceDefinition::factory(
            PeopleProfileSecurityNotificationService::class,
            self::ID,
            [MultiFactorNotificationTargetRepository::class, AccountSecurityNotificationRepository::class, SecurityNotificationDeduplicationKeyFactory::class, SecurityNotificationConfiguration::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PeopleProfileSecurityNotificationService => new PeopleProfileSecurityNotificationService(
                ServiceReference::get($r, MultiFactorNotificationTargetRepository::class),
                ServiceReference::get($r, AccountSecurityNotificationRepository::class),
                ServiceReference::get($r, SecurityNotificationDeduplicationKeyFactory::class),
                ServiceReference::get($r, SecurityNotificationConfiguration::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            PersonProfileService::class,
            self::ID,
            [PersonProfileRepository::class, IdentityAccessRepository::class, IdentityRateLimiter::class, IdentityFingerprintGenerator::class,
                StepUpGuard::class, SecurityAuditRecorder::class, PeopleProfileSecurityNotificationService::class,
                PersonRegistryCodeGenerator::class, PeopleProfilesConfiguration::class, PersonProfileAccessPolicy::class, TransactionManager::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PersonProfileService => new PersonProfileService(
                ServiceReference::get($r, PersonProfileRepository::class),
                ServiceReference::get($r, IdentityAccessRepository::class),
                ServiceReference::get($r, IdentityRateLimiter::class),
                ServiceReference::get($r, IdentityFingerprintGenerator::class),
                ServiceReference::get($r, StepUpGuard::class),
                ServiceReference::get($r, SecurityAuditRecorder::class),
                ServiceReference::get($r, PeopleProfileSecurityNotificationService::class),
                ServiceReference::get($r, PersonRegistryCodeGenerator::class),
                ServiceReference::get($r, PeopleProfilesConfiguration::class),
                ServiceReference::get($r, PersonProfileAccessPolicy::class),
                ServiceReference::get($r, TransactionManager::class),
                ServiceReference::get($r, Clock::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            PeopleProfilesReadinessCheck::class,
            self::ID,
            [PeopleProfilesConfiguration::class, SchemaHealthCheck::class, PeopleProfilesVerificationProbe::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PeopleProfilesReadinessCheck => new PeopleProfilesReadinessCheck(ServiceReference::get($r, PeopleProfilesConfiguration::class), ServiceReference::get($r, SchemaHealthCheck::class), ServiceReference::get($r, PeopleProfilesVerificationProbe::class))),
        ));
        $context->service(ServiceDefinition::factory(
            PeopleProfilesVerifyConsoleCommand::class,
            self::ID,
            [PeopleProfilesConfiguration::class, PeopleProfilesReadinessCheck::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PeopleProfilesVerifyConsoleCommand => new PeopleProfilesVerifyConsoleCommand(ServiceReference::get($r, PeopleProfilesConfiguration::class), ServiceReference::get($r, PeopleProfilesReadinessCheck::class))),
        ));
        $context->service(ServiceDefinition::factory(
            PeopleProfilesController::class,
            self::ID,
            [AuthenticatedRequestGuard::class, IdentityCsrf::class, IdentityAccessView::class, PersonProfileService::class, PeopleProfilesConfiguration::class, Clock::class, NigeriaGeographyDirectoryHandler::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PeopleProfilesController => new PeopleProfilesController(
                ServiceReference::get($r, AuthenticatedRequestGuard::class),
                ServiceReference::get($r, IdentityCsrf::class),
                ServiceReference::get($r, IdentityAccessView::class),
                ServiceReference::get($r, PersonProfileService::class),
                ServiceReference::get($r, PeopleProfilesConfiguration::class),
                ServiceReference::get($r, Clock::class),
                ServiceReference::get($r, NigeriaGeographyDirectoryHandler::class),
            )),
        ));
    }
}
