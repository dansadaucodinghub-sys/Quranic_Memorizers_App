<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\SecurityAudit\Infrastructure\Persistence\HashChainedSecurityAuditRecorder;
use Qmdb\Modules\SecurityAudit\Application\ScheduledSecurityAuditCheckpointTask;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditHashChain;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditRecorder;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditControlVerifier;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditReadinessCheck;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Infrastructure\Persistence\SecurityAuditCheckpointService;
use Qmdb\Modules\SecurityAudit\Infrastructure\Persistence\SecurityAuditVerifier;
use Qmdb\Modules\SecurityAudit\Configuration\SecurityAuditConfiguration;
use Qmdb\Modules\SecurityAudit\Domain\CanonicalSecurityEventMetadataSerializer as MetadataSerializer;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditIntegrityKeyProvider;
use Qmdb\Modules\SecurityAudit\Infrastructure\Security\EnvironmentSecurityAuditIntegrityKeyProvider;
use Qmdb\Modules\SecurityAudit\Infrastructure\Persistence\MySqlSecurityAuditRepository;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditRepository;
use Qmdb\Modules\SecurityAudit\Interface\Console\SecurityAuditCheckpointConsoleCommand;
use Qmdb\Modules\SecurityAudit\Interface\Console\SecurityAuditVerifyConsoleCommand;
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
use Qmdb\Shared\Security\Secrets\SecretsProvider;
use Qmdb\Shared\Time\Clock;

final readonly class SecurityAuditModule implements Module
{
    private const string ID = 'security.audit';

    public function __construct(private SecurityAuditConfiguration $configuration)
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
            new ModuleId('foundation.database'), new ModuleId('foundation.schema'), new ModuleId('foundation.background'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(SecurityAuditConfiguration::class, self::ID, $this->configuration));
        $context->service(ServiceDefinition::instance(SecurityAuditHashChain::class, self::ID, new SecurityAuditHashChain()));
        $context->service(ServiceDefinition::factory(
            MetadataSerializer::class,
            self::ID,
            [SecurityAuditConfiguration::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MetadataSerializer => new MetadataSerializer(
                ServiceReference::get($r, SecurityAuditConfiguration::class)->metadataMaximumBytes,
            )),
        ));
        $context->service(ServiceDefinition::factory(
            EnvironmentSecurityAuditIntegrityKeyProvider::class,
            self::ID,
            [SecretsProvider::class, SecurityAuditConfiguration::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): EnvironmentSecurityAuditIntegrityKeyProvider =>
                new EnvironmentSecurityAuditIntegrityKeyProvider(
                    ServiceReference::get($r, SecretsProvider::class),
                    ServiceReference::get($r, SecurityAuditConfiguration::class),
                )),
        ));
        $context->alias(SecurityAuditIntegrityKeyProvider::class, EnvironmentSecurityAuditIntegrityKeyProvider::class);
        $context->service(ServiceDefinition::factory(
            HashChainedSecurityAuditRecorder::class,
            self::ID,
            [DatabaseConnectionProvider::class, MetadataSerializer::class,
                SecurityAuditIntegrityKeyProvider::class, SecurityAuditConfiguration::class, SecurityAuditHashChain::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): HashChainedSecurityAuditRecorder => new HashChainedSecurityAuditRecorder(
                ServiceReference::get($r, DatabaseConnectionProvider::class),
                ServiceReference::get($r, MetadataSerializer::class),
                ServiceReference::get($r, SecurityAuditIntegrityKeyProvider::class),
                ServiceReference::get($r, SecurityAuditConfiguration::class),
                ServiceReference::get($r, SecurityAuditHashChain::class),
            )),
        ));
        $context->alias(SecurityAuditRecorder::class, HashChainedSecurityAuditRecorder::class);
        $context->service(ServiceDefinition::factory(
            SecurityAuditEventAppender::class,
            self::ID,
            [SecurityAuditRecorder::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): SecurityAuditEventAppender => new SecurityAuditEventAppender(
                ServiceReference::get($r, SecurityAuditRecorder::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlSecurityAuditRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlSecurityAuditRepository => new MySqlSecurityAuditRepository(
                ServiceReference::get($r, DatabaseConnectionProvider::class),
            )),
        ));
        $context->alias(SecurityAuditRepository::class, MySqlSecurityAuditRepository::class);
        $context->service(ServiceDefinition::factory(
            SecurityAuditCheckpointService::class,
            self::ID,
            [DatabaseConnectionProvider::class, TransactionManager::class, SecurityAuditIntegrityKeyProvider::class,
                SecurityAuditConfiguration::class, SecurityAuditHashChain::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): SecurityAuditCheckpointService => new SecurityAuditCheckpointService(
                ServiceReference::get($r, DatabaseConnectionProvider::class),
                ServiceReference::get($r, TransactionManager::class),
                ServiceReference::get($r, SecurityAuditIntegrityKeyProvider::class),
                ServiceReference::get($r, SecurityAuditConfiguration::class),
                ServiceReference::get($r, SecurityAuditHashChain::class),
                ServiceReference::get($r, Clock::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            SecurityAuditVerifier::class,
            self::ID,
            [DatabaseConnectionProvider::class, SecurityAuditIntegrityKeyProvider::class, SecurityAuditHashChain::class,
                SecurityAuditConfiguration::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): SecurityAuditVerifier => new SecurityAuditVerifier(
                ServiceReference::get($r, DatabaseConnectionProvider::class),
                ServiceReference::get($r, SecurityAuditIntegrityKeyProvider::class),
                ServiceReference::get($r, SecurityAuditHashChain::class),
                ServiceReference::get($r, SecurityAuditConfiguration::class),
            )),
        ));
        $context->alias(SecurityAuditControlVerifier::class, SecurityAuditVerifier::class);
        $context->service(ServiceDefinition::factory(
            SecurityAuditReadinessCheck::class,
            self::ID,
            [SecurityAuditControlVerifier::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): SecurityAuditReadinessCheck =>
                new SecurityAuditReadinessCheck(ServiceReference::get($r, SecurityAuditControlVerifier::class))),
        ));
        $context->service(ServiceDefinition::factory(
            ScheduledSecurityAuditCheckpointTask::class,
            self::ID,
            [SecurityAuditCheckpointService::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): ScheduledSecurityAuditCheckpointTask =>
                new ScheduledSecurityAuditCheckpointTask(ServiceReference::get($r, SecurityAuditCheckpointService::class))),
        ));
        $context->service(ServiceDefinition::factory(
            SecurityAuditVerifyConsoleCommand::class,
            self::ID,
            [SecurityAuditVerifier::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): SecurityAuditVerifyConsoleCommand =>
                new SecurityAuditVerifyConsoleCommand(ServiceReference::get($r, SecurityAuditVerifier::class))),
        ));
        $context->service(ServiceDefinition::factory(
            SecurityAuditCheckpointConsoleCommand::class,
            self::ID,
            [SecurityAuditCheckpointService::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): SecurityAuditCheckpointConsoleCommand =>
                new SecurityAuditCheckpointConsoleCommand(ServiceReference::get($r, SecurityAuditCheckpointService::class))),
        ));
        $context->scheduledTask(new ScheduledTaskRegistration(
            new ScheduledTaskId('security.audit.checkpoint'),
            'Create deterministic checkpoint evidence for changed security audit streams.',
            new FixedIntervalSchedule($this->configuration->checkpointIntervalSeconds),
            ScheduledSecurityAuditCheckpointTask::class,
            max(120, $this->configuration->checkpointIntervalSeconds),
            self::ID,
        ));
    }
}
