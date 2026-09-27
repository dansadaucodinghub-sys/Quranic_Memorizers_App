<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\ProductionHardening\Application\ScheduledP12MaintenanceTask;
use Qmdb\Modules\ProductionHardening\Application\WebhookTransport;
use Qmdb\Modules\ProductionHardening\Application\WebhookSubscriptionSecretIssuer;
use Qmdb\Modules\ProductionHardening\Domain\ApiCredentialIssuer;
use Qmdb\Modules\ProductionHardening\Domain\BoundedRetryPolicy;
use Qmdb\Modules\ProductionHardening\Domain\IntegrationSecretBox;
use Qmdb\Modules\ProductionHardening\Domain\OperationalLogRedactor;
use Qmdb\Modules\ProductionHardening\Domain\RetentionDispositionPolicy;
use Qmdb\Modules\ProductionHardening\Domain\WebhookEndpointPolicy;
use Qmdb\Modules\ProductionHardening\Domain\WebhookSignature;
use Qmdb\Modules\ProductionHardening\Infrastructure\Http\NativeWebhookTransport;
use Qmdb\Modules\ProductionHardening\Infrastructure\Persistence\MySqlProductionHardeningRepository;
use Qmdb\Modules\ProductionHardening\Infrastructure\Persistence\P12MaintenanceService;
use Qmdb\Modules\ProductionHardening\Interface\Console\CompetitionP12ProductionReadinessConsoleCommand;
use Qmdb\Modules\ProductionHardening\Interface\Console\CompetitionP12ProductionSmokeConsoleCommand;
use Qmdb\Modules\ProductionHardening\Interface\Console\CompetitionP12VerifyConsoleCommand;
use Qmdb\Modules\ProductionHardening\Interface\Http\P12PortalController;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Background\Scheduler\FixedIntervalSchedule;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskId;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRegistration;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class ProductionHardeningModule implements Module
{
    private const string ID = 'production.hardening';

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('search.analytics_reporting'), new ModuleId('community.recitation_clips'),
            new ModuleId('security.authorization'), new ModuleId('security.audit'),
            new ModuleId('identity.multifactor'), new ModuleId('identity.sessions'),
            new ModuleId('tenancy.context'), new ModuleId('foundation.presentation'),
            new ModuleId('foundation.database'), new ModuleId('foundation.background'),
            new ModuleId('foundation.observability'), new ModuleId('foundation.core'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(ApiCredentialIssuer::class, self::ID, new ApiCredentialIssuer()));
        $context->service(ServiceDefinition::instance(BoundedRetryPolicy::class, self::ID, new BoundedRetryPolicy()));
        $context->service(ServiceDefinition::instance(WebhookEndpointPolicy::class, self::ID, new WebhookEndpointPolicy()));
        $context->service(ServiceDefinition::instance(WebhookSignature::class, self::ID, new WebhookSignature()));
        $context->service(ServiceDefinition::instance(RetentionDispositionPolicy::class, self::ID, new RetentionDispositionPolicy()));
        $context->service(ServiceDefinition::instance(OperationalLogRedactor::class, self::ID, new OperationalLogRedactor()));
        $context->service(ServiceDefinition::factory(
            IntegrationSecretBox::class,
            self::ID,
            [EnvironmentVariables::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): IntegrationSecretBox => new IntegrationSecretBox(ServiceReference::get($resolver, EnvironmentVariables::class))),
        ));
        $context->service(ServiceDefinition::factory(
            WebhookSubscriptionSecretIssuer::class,
            self::ID,
            [IntegrationSecretBox::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): WebhookSubscriptionSecretIssuer => new WebhookSubscriptionSecretIssuer(ServiceReference::get($resolver, IntegrationSecretBox::class))),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlProductionHardeningRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class, ApiCredentialIssuer::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlProductionHardeningRepository => new MySqlProductionHardeningRepository(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                ServiceReference::get($resolver, ApiCredentialIssuer::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            NativeWebhookTransport::class,
            self::ID,
            [WebhookEndpointPolicy::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): NativeWebhookTransport => new NativeWebhookTransport(ServiceReference::get($resolver, WebhookEndpointPolicy::class))),
        ));
        $context->service(ServiceDefinition::factory(
            WebhookTransport::class,
            self::ID,
            [NativeWebhookTransport::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): WebhookTransport => ServiceReference::get($resolver, NativeWebhookTransport::class)),
        ));
        $context->service(ServiceDefinition::factory(
            P12MaintenanceService::class,
            self::ID,
            [MySqlProductionHardeningRepository::class, WebhookEndpointPolicy::class, WebhookSignature::class,
                IntegrationSecretBox::class, BoundedRetryPolicy::class, WebhookTransport::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): P12MaintenanceService => new P12MaintenanceService(
                ServiceReference::get($resolver, MySqlProductionHardeningRepository::class),
                ServiceReference::get($resolver, WebhookEndpointPolicy::class),
                ServiceReference::get($resolver, WebhookSignature::class),
                ServiceReference::get($resolver, IntegrationSecretBox::class),
                ServiceReference::get($resolver, BoundedRetryPolicy::class),
                ServiceReference::get($resolver, WebhookTransport::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            ScheduledP12MaintenanceTask::class,
            self::ID,
            [P12MaintenanceService::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): ScheduledP12MaintenanceTask => new ScheduledP12MaintenanceTask(ServiceReference::get($resolver, P12MaintenanceService::class))),
        ));
        foreach (
            [
            ['outbox.publish', 'Publish bounded transactional outbox events.', 15, 60],
            ['notifications.deliver', 'Deliver bounded policy-governed notifications.', 60, 120],
            ['webhooks.deliver', 'Deliver bounded signed webhooks.', 30, 120],
            ['p12.work.reconcile', 'Recover expired P12 work leases.', 300, 180],
            ['privacy.retention.process', 'Apply hold-aware retention dispositions.', 86400, 3600],
            ['operations.cleanup', 'Remove expired transient operational records.', 900, 300],
            ['audit.lineage.verify', 'Verify generic audit lineage.', 3600, 600],
            ['backups.metadata.verify', 'Verify registered backup metadata evidence.', 86400, 1800],
            ] as [$id, $description, $interval, $lease]
        ) {
            $context->scheduledTask(new ScheduledTaskRegistration(new ScheduledTaskId($id), $description, new FixedIntervalSchedule($interval), ScheduledP12MaintenanceTask::class, $lease, self::ID));
        }
        $context->service(ServiceDefinition::factory(
            P12PortalController::class,
            self::ID,
            [MySqlProductionHardeningRepository::class, WebhookEndpointPolicy::class, WebhookSubscriptionSecretIssuer::class,
                AuthenticatedRequestGuard::class, TenantContextRequiredGuard::class, IdentityCsrf::class,
                IdentityAccessView::class, Psr17Factory::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): P12PortalController => new P12PortalController(
                ServiceReference::get($resolver, MySqlProductionHardeningRepository::class),
                ServiceReference::get($resolver, WebhookEndpointPolicy::class),
                ServiceReference::get($resolver, WebhookSubscriptionSecretIssuer::class),
                ServiceReference::get($resolver, AuthenticatedRequestGuard::class),
                ServiceReference::get($resolver, TenantContextRequiredGuard::class),
                ServiceReference::get($resolver, IdentityCsrf::class),
                ServiceReference::get($resolver, IdentityAccessView::class),
                ServiceReference::get($resolver, Psr17Factory::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(CompetitionP12VerifyConsoleCommand::class, self::ID, [MySqlProductionHardeningRepository::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP12VerifyConsoleCommand => new CompetitionP12VerifyConsoleCommand(ServiceReference::get($resolver, MySqlProductionHardeningRepository::class)))));
        $context->service(ServiceDefinition::factory(CompetitionP12ProductionReadinessConsoleCommand::class, self::ID, [MySqlProductionHardeningRepository::class, ScheduledTaskMap::class, ApplicationConfiguration::class, IntegrationSecretBox::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP12ProductionReadinessConsoleCommand => new CompetitionP12ProductionReadinessConsoleCommand(ServiceReference::get($resolver, MySqlProductionHardeningRepository::class), ServiceReference::get($resolver, ScheduledTaskMap::class), ServiceReference::get($resolver, ApplicationConfiguration::class), ServiceReference::get($resolver, IntegrationSecretBox::class)))));
        $context->service(ServiceDefinition::factory(CompetitionP12ProductionSmokeConsoleCommand::class, self::ID, [MySqlProductionHardeningRepository::class, P12MaintenanceService::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP12ProductionSmokeConsoleCommand => new CompetitionP12ProductionSmokeConsoleCommand(ServiceReference::get($resolver, MySqlProductionHardeningRepository::class), ServiceReference::get($resolver, P12MaintenanceService::class)))));
    }
}
