<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Bootstrap;

use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\Application;
use Qmdb\Bootstrap\Console\ConsoleApplication;
use Qmdb\Bootstrap\Http\HttpRuntime;
use Qmdb\Bootstrap\Module\ApplicationServicesFoundationModule;
use Qmdb\Bootstrap\Module\ApplicationHttpModule;
use Qmdb\Bootstrap\Module\BackgroundExecutionFoundationModule;
use Qmdb\Bootstrap\Module\ConsoleFoundationModule;
use Qmdb\Bootstrap\Module\CompetitionConfigurationModule;
use Qmdb\Bootstrap\Module\CompetitionRegistrationModule;
use Qmdb\Bootstrap\Module\CompetitionJudgingModule;
use Qmdb\Bootstrap\Module\CompetitionScoringModule;
use Qmdb\Bootstrap\Module\CompetitionResultsModule;
use Qmdb\Bootstrap\Module\CompetitionLiveModule;
use Qmdb\Bootstrap\Module\CompetitionPublicationModule;
use Qmdb\Bootstrap\Module\CompetitionAppealAdjudicationModule;
use Qmdb\Bootstrap\Module\CoreFoundationModule;
use Qmdb\Bootstrap\Module\DatabaseFoundationModule;
use Qmdb\Bootstrap\Module\GeographyReferenceModule;
use Qmdb\Bootstrap\Module\HttpFoundationModule;
use Qmdb\Bootstrap\Module\IdentityAccessModule;
use Qmdb\Bootstrap\Module\IdentityAccountStateModule;
use Qmdb\Bootstrap\Module\IdentityFoundationModule;
use Qmdb\Bootstrap\Module\IdentityRecoveryModule;
use Qmdb\Bootstrap\Module\IdentitySecurityNotificationsModule;
use Qmdb\Bootstrap\Module\IdentitySessionsModule;
use Qmdb\Bootstrap\Module\IdentityMultiFactorModule;
use Qmdb\Bootstrap\Module\ObservabilityFoundationModule;
use Qmdb\Bootstrap\Module\OrganizationsRegistryModule;
use Qmdb\Bootstrap\Module\OrganizationsAffiliationsModule;
use Qmdb\Bootstrap\Module\PeopleProfilesModule;
use Qmdb\Bootstrap\Module\PeopleIdentityResolutionModule;
use Qmdb\Bootstrap\Module\PresentationFoundationModule;
use Qmdb\Bootstrap\Module\QuranReferenceGovernanceModule;
use Qmdb\Bootstrap\Module\SchemaFoundationModule;
use Qmdb\Bootstrap\Module\SecurityWebModule;
use Qmdb\Bootstrap\Module\SecurityAuthorizationModule;
use Qmdb\Bootstrap\Module\SecurityAuditModule;
use Qmdb\Bootstrap\Module\SecurityPrivilegedAccessModule;
use Qmdb\Bootstrap\Module\TenancyFoundationModule;
use Qmdb\Bootstrap\Module\TenancyContextModule;
use Qmdb\Bootstrap\RuntimeEnvironment;
use Qmdb\Shared\Application\Query\QueryBus;
use Qmdb\Shared\Application\System\GetSystemInformation;
use Qmdb\Shared\Application\System\SystemInformation;
use Qmdb\Shared\Background\Configuration\BackgroundExecutionConfigurationFactory;
use Qmdb\Shared\Configuration\ApplicationConfigurationFactory;
use Qmdb\Shared\Configuration\ConfigurationSource;
use Qmdb\Shared\Configuration\Database\DatabaseConfigurationFactory;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Configuration\Logging\LoggingConfigurationFactory;
use Qmdb\Modules\IdentityAccess\Configuration\IdentityAccessConfigurationFactory;
use Qmdb\Modules\IdentityAccountState\Configuration\AccountStateConfigurationFactory;
use Qmdb\Modules\IdentityRecovery\Configuration\IdentityRecoveryConfigurationFactory;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfigurationFactory;
use Qmdb\Modules\IdentitySessions\Configuration\IdentitySessionConfigurationFactory;
use Qmdb\Modules\IdentityMultiFactor\Configuration\IdentityMultiFactorConfigurationFactory;
use Qmdb\Modules\People\Configuration\PeopleProfilesConfigurationFactory;
use Qmdb\Modules\Organizations\Configuration\OrganizationsRegistryConfigurationFactory;
use Qmdb\Modules\OrganizationAffiliations\Configuration\OrganizationAffiliationsConfigurationFactory;
use Qmdb\Modules\IdentityResolution\Configuration\IdentityResolutionConfigurationFactory;
use Qmdb\Modules\SecurityPrivilegedAccess\Configuration\PrivilegedAccessConfigurationFactory;
use Qmdb\Modules\SecurityAudit\Configuration\SecurityAuditConfigurationFactory;
use Qmdb\Shared\DependencyInjection\CompiledContainer;
use Qmdb\Shared\DependencyInjection\ContainerBuilder;
use Qmdb\Shared\Module\ModuleRegistry;

final class FoundationCompilationTest extends TestCase
{
    public function testAllFoundationModulesCompileInDeterministicOrder(): void
    {
        [$registry] = $this->compile();

        self::assertSame([
            'foundation.core',
            'foundation.database',
            'foundation.application',
            'foundation.observability',
            'foundation.presentation',
            'foundation.schema',
            'foundation.http',
            'identity.accounts',
            'security.web',
            'identity.access',
            'foundation.background',
            'identity.security_notifications',
            'security.audit',
            'identity.sessions',
            'identity.multifactor',
            'reference.geography',
            'tenancy.workspaces',
            'security.authorization',
            'tenancy.context',
            'organizations.registry',
            'quran.reference_governance',
            'competition.configuration',
            'people.profiles',
            'organizations.affiliations',
            'competition.registration',
            'competition.judging',
            'competition.scoring',
            'competition.results',
            'competition.live_operations',
            'competition.result_publication',
            'certificate.issuance',
            'certificate.verification',
            'competition.appeal_adjudication',
            'identity.recovery',
            'security.privileged_access',
            'identity.account_state',
            'media.catalog',
            'media.ingestion',
            'media.moderation',
            'media.delivery',
            'people.identity_resolution',
            'application.http',
            'media.processing',
            'record.passport',
            'trusted.archive',
            'foundation.console',
        ], $registry->orderedModuleIds());
    }

    public function testRootRuntimeServicesResolveAndRemainShared(): void
    {
        [, $container] = $this->compile();

        self::assertInstanceOf(Application::class, $container->get(Application::class));
        self::assertInstanceOf(ConsoleApplication::class, $container->get(ConsoleApplication::class));
        self::assertInstanceOf(HttpRuntime::class, $container->get(HttpRuntime::class));
        self::assertSame($container->get(QueryBus::class), $container->get(QueryBus::class));
    }

    public function testSystemInformationIsDeliveredThroughCompiledQueryBus(): void
    {
        [, $container] = $this->compile();
        $queryBus = $container->get(QueryBus::class);
        self::assertInstanceOf(QueryBus::class, $queryBus);
        $result = $queryBus->ask(new GetSystemInformation());

        self::assertInstanceOf(SystemInformation::class, $result);
        self::assertSame('QMDB-P3-B06', $result->currentBatch());
    }

    public function testFoundationContainsNoDeferredInfrastructureService(): void
    {
        [, $container] = $this->compile();

        self::assertFalse($container->has('PDO'));
        self::assertFalse($container->has('Redis'));
        self::assertFalse($container->has('Qmdb\\Modules\\Identity\\IdentityModule'));
    }

    /** @return array{ModuleRegistry, CompiledContainer} */
    private function compile(): array
    {
        $variables = new EnvironmentVariables([
            'APP_ENV' => 'test',
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => 'UTC',
            'DB_HOST' => '127.0.0.1',
            'DB_NAME' => 'qmdb_test',
            'DB_USERNAME' => 'qmdb_test',
            'APP_PUBLIC_BASE_URL' => 'http://127.0.0.1:8080',
            'AUTH_CSRF_SIGNING_KEY' => 'test-csrf-signing-key-with-at-least-32-bytes',
            'AUTH_IDENTITY_HMAC_KEY' => 'test-identity-hmac-key-with-at-least-32-bytes',
            'AUTH_CONTACT_ENCRYPTION_KEY' => 'Y2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2M=',
            'AUTH_MFA_ENCRYPTION_KEY' => 'bW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW0=',
            'AUTH_SECURITY_AUDIT_HMAC_KEY' => 'test-security-audit-hmac-key-with-at-least-32-bytes',
            'MAILER_DSN' => 'null://null',
        ]);
        $configuration = (new ApplicationConfigurationFactory())->create(
            $variables,
            ConfigurationSource::PROCESS,
        );
        $identityAccess = (new IdentityAccessConfigurationFactory())->create($variables, $configuration);
        $identitySessions = (new IdentitySessionConfigurationFactory())->create($variables, $configuration);
        $identityRecovery = (new IdentityRecoveryConfigurationFactory())->create($variables);
        $securityNotifications = (new SecurityNotificationConfigurationFactory())->create($variables);
        $identityMultiFactor = (new IdentityMultiFactorConfigurationFactory())->create($variables, $configuration);
        $privilegedAccess = (new PrivilegedAccessConfigurationFactory())->create($variables);
        $securityAudit = (new SecurityAuditConfigurationFactory())->create($variables, $configuration);
        $accountState = (new AccountStateConfigurationFactory())->create($variables);
        $peopleProfiles = (new PeopleProfilesConfigurationFactory())->create($variables);
        $organizationsRegistry = (new OrganizationsRegistryConfigurationFactory())->create($variables);
        $organizationAffiliations = (new OrganizationAffiliationsConfigurationFactory())->create($variables);
        $identityResolution = (new IdentityResolutionConfigurationFactory())->create($variables, $configuration);
        $registry = new ModuleRegistry([
            new CoreFoundationModule(
                $configuration,
                $variables,
                new RuntimeEnvironment('8.5.0', ['json', 'mbstring']),
            ),
            new ApplicationServicesFoundationModule(),
            new ObservabilityFoundationModule(
                (new LoggingConfigurationFactory())->create($variables, $configuration->environment()),
            ),
            new DatabaseFoundationModule(
                (new DatabaseConfigurationFactory())->create($variables, $configuration->environment()),
            ),
            new IdentityFoundationModule(),
            new TenancyFoundationModule(),
            new SchemaFoundationModule(dirname(__DIR__, 3)),
            new BackgroundExecutionFoundationModule(
                (new BackgroundExecutionConfigurationFactory())->create($variables),
            ),
            new PresentationFoundationModule(dirname(__DIR__, 3)),
            new HttpFoundationModule(dirname(__DIR__, 3)),
            new GeographyReferenceModule(dirname(__DIR__, 3)),
            new SecurityWebModule($identityAccess),
            new IdentityAccessModule($identityAccess),
            new IdentitySessionsModule($identitySessions, $identityMultiFactor),
            new IdentitySecurityNotificationsModule($securityNotifications, $identityAccess),
            new IdentityRecoveryModule($identityRecovery, $identityAccess),
            new IdentityMultiFactorModule(),
            new SecurityAuthorizationModule(),
            new TenancyContextModule(dirname(__DIR__, 3)),
            new SecurityPrivilegedAccessModule($privilegedAccess),
            new SecurityAuditModule($securityAudit),
            new IdentityAccountStateModule($accountState),
            new PeopleProfilesModule($peopleProfiles),
            new OrganizationsRegistryModule($organizationsRegistry),
            new OrganizationsAffiliationsModule($organizationAffiliations),
            new PeopleIdentityResolutionModule($identityResolution),
            new QuranReferenceGovernanceModule(dirname(__DIR__, 3)),
            new CompetitionConfigurationModule(),
            new CompetitionRegistrationModule(),
            new CompetitionJudgingModule(),
            new CompetitionScoringModule(),
            new CompetitionResultsModule(),
            new CompetitionLiveModule(),
            new CompetitionPublicationModule(),
            new CompetitionAppealAdjudicationModule(),
            new \Qmdb\Bootstrap\Module\CertificateIssuanceModule(),
            new \Qmdb\Bootstrap\Module\CertificateVerificationModule(),
            new \Qmdb\Bootstrap\Module\RecordPassportModule(),
            new \Qmdb\Bootstrap\Module\TrustedArchiveModule(),
            new \Qmdb\Bootstrap\Module\MediaCatalogModule(),
            new \Qmdb\Bootstrap\Module\MediaIngestionModule(),
            new \Qmdb\Bootstrap\Module\MediaProcessingModule(),
            new \Qmdb\Bootstrap\Module\MediaModerationModule(),
            new \Qmdb\Bootstrap\Module\MediaDeliveryModule(),
            new ApplicationHttpModule(dirname(__DIR__, 3)),
            new ConsoleFoundationModule(),
        ]);
        $builder = new ContainerBuilder();
        $compilation = $registry->compile($builder);

        return [$registry, $builder->build($compilation->moduleDependencies)];
    }
}
