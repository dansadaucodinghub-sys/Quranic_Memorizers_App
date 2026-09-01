<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap;

use InvalidArgumentException;
use Qmdb\Bootstrap\Console\ConsoleApplication;
use Qmdb\Bootstrap\Http\HttpRuntime;
use Qmdb\Bootstrap\Module\ApplicationServicesFoundationModule;
use Qmdb\Bootstrap\Module\ApplicationHttpModule;
use Qmdb\Bootstrap\Module\BackgroundExecutionFoundationModule;
use Qmdb\Bootstrap\Module\ConsoleFoundationModule;
use Qmdb\Bootstrap\Module\CoreFoundationModule;
use Qmdb\Bootstrap\Module\DatabaseFoundationModule;
use Qmdb\Bootstrap\Module\HttpFoundationModule;
use Qmdb\Bootstrap\Module\GeographyReferenceModule;
use Qmdb\Bootstrap\Module\IdentityFoundationModule;
use Qmdb\Bootstrap\Module\IdentityAccessModule;
use Qmdb\Bootstrap\Module\IdentityAccountStateModule;
use Qmdb\Bootstrap\Module\IdentitySessionsModule;
use Qmdb\Bootstrap\Module\IdentityRecoveryModule;
use Qmdb\Bootstrap\Module\IdentityMultiFactorModule;
use Qmdb\Bootstrap\Module\SecurityAuthorizationModule;
use Qmdb\Bootstrap\Module\SecurityPrivilegedAccessModule;
use Qmdb\Bootstrap\Module\SecurityAuditModule;
use Qmdb\Bootstrap\Module\IdentitySecurityNotificationsModule;
use Qmdb\Bootstrap\Module\PeopleProfilesModule;
use Qmdb\Bootstrap\Module\ObservabilityFoundationModule;
use Qmdb\Bootstrap\Module\PresentationFoundationModule;
use Qmdb\Bootstrap\Module\SchemaFoundationModule;
use Qmdb\Bootstrap\Module\SecurityWebModule;
use Qmdb\Bootstrap\Module\TenancyFoundationModule;
use Qmdb\Bootstrap\Module\TenancyContextModule;
use Qmdb\Shared\Background\Configuration\BackgroundExecutionConfigurationFactory;
use Qmdb\Shared\Configuration\ApplicationConfigurationFactory;
use Qmdb\Shared\Configuration\Database\DatabaseConfigurationFactory;
use Qmdb\Shared\Configuration\EnvironmentLoader;
use Qmdb\Shared\Configuration\Infrastructure\DotenvEnvironmentLoader;
use Qmdb\Shared\Configuration\Logging\LoggingConfigurationFactory;
use Qmdb\Modules\IdentityAccess\Configuration\IdentityAccessConfigurationFactory;
use Qmdb\Modules\IdentitySessions\Configuration\IdentitySessionConfigurationFactory;
use Qmdb\Modules\IdentityRecovery\Configuration\IdentityRecoveryConfigurationFactory;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfigurationFactory;
use Qmdb\Modules\IdentityMultiFactor\Configuration\IdentityMultiFactorConfigurationFactory;
use Qmdb\Modules\SecurityPrivilegedAccess\Configuration\PrivilegedAccessConfigurationFactory;
use Qmdb\Modules\SecurityAudit\Configuration\SecurityAuditConfigurationFactory;
use Qmdb\Modules\IdentityAccountState\Configuration\AccountStateConfigurationFactory;
use Qmdb\Modules\People\Configuration\PeopleProfilesConfigurationFactory;
use Qmdb\Shared\DependencyInjection\CompiledContainer;
use Qmdb\Shared\DependencyInjection\ContainerBuilder;
use Qmdb\Shared\Module\ModuleRegistry;
use RuntimeException;

final readonly class ApplicationFactory
{
    public function __construct(
        private string $projectRoot,
        private EnvironmentLoader $environmentLoader,
        private ApplicationConfigurationFactory $configurationFactory,
    ) {
    }

    public static function fromCurrentProcess(?string $projectRoot = null): self
    {
        return new self(
            projectRoot: $projectRoot ?? dirname(__DIR__, 2),
            environmentLoader: new DotenvEnvironmentLoader(),
            configurationFactory: new ApplicationConfigurationFactory(),
        );
    }

    /** @param list<string>|null $loadedExtensions */
    public function create(?string $phpVersion = null, ?array $loadedExtensions = null): Application
    {
        $application = $this->compose($phpVersion, $loadedExtensions)->get(Application::class);
        if (!$application instanceof Application) {
            throw new RuntimeException('Application root service is invalid.');
        }

        return $application;
    }

    /** @param list<string>|null $loadedExtensions */
    public function createHttpRuntime(?string $phpVersion = null, ?array $loadedExtensions = null): HttpRuntime
    {
        $runtime = $this->compose($phpVersion, $loadedExtensions)->get(HttpRuntime::class);
        if (!$runtime instanceof HttpRuntime) {
            throw new RuntimeException('HTTP root service is invalid.');
        }

        return $runtime;
    }

    /** @param list<string>|null $loadedExtensions */
    public function createConsoleApplication(
        ?string $phpVersion = null,
        ?array $loadedExtensions = null,
    ): ConsoleApplication {
        $console = $this->compose($phpVersion, $loadedExtensions)->get(ConsoleApplication::class);
        if (!$console instanceof ConsoleApplication) {
            throw new RuntimeException('Console root service is invalid.');
        }

        return $console;
    }

    /** @param list<string>|null $loadedExtensions */
    private function compose(?string $phpVersion, ?array $loadedExtensions): CompiledContainer
    {
        if (($phpVersion === null) !== ($loadedExtensions === null)) {
            throw new InvalidArgumentException(
                'A supplied runtime version and extension list must be provided together.',
            );
        }

        $effectivePhpVersion = $phpVersion ?? PHP_VERSION;
        $effectiveExtensions = $loadedExtensions ?? get_loaded_extensions();
        $runtimeRequirements = new RuntimeRequirements();
        $runtimeResult = $runtimeRequirements->evaluate($effectivePhpVersion, $effectiveExtensions);
        if (!$runtimeResult->isSatisfied()) {
            throw new RuntimeException($runtimeResult->toCliString());
        }

        $loadedEnvironment = $this->environmentLoader->load($this->projectRoot);
        $configuration = $this->configurationFactory->create(
            $loadedEnvironment->variables(),
            $loadedEnvironment->source(),
        );
        $runtimeEnvironment = new RuntimeEnvironment($effectivePhpVersion, $effectiveExtensions);
        $loggingConfiguration = (new LoggingConfigurationFactory())->create(
            $loadedEnvironment->variables(),
            $configuration->environment(),
        );
        $databaseConfiguration = (new DatabaseConfigurationFactory())->create(
            $loadedEnvironment->variables(),
            $configuration->environment(),
        );
        $backgroundConfiguration = (new BackgroundExecutionConfigurationFactory())->create(
            $loadedEnvironment->variables(),
        );
        $identityAccessConfiguration = (new IdentityAccessConfigurationFactory())->create(
            $loadedEnvironment->variables(),
            $configuration,
        );
        $identitySessionConfiguration = (new IdentitySessionConfigurationFactory())->create(
            $loadedEnvironment->variables(),
            $configuration,
        );
        $identityRecoveryConfiguration = (new IdentityRecoveryConfigurationFactory())->create(
            $loadedEnvironment->variables(),
        );
        $securityNotificationConfiguration = (new SecurityNotificationConfigurationFactory())->create(
            $loadedEnvironment->variables(),
        );
        $identityMultiFactorConfiguration = (new IdentityMultiFactorConfigurationFactory())->create(
            $loadedEnvironment->variables(),
            $configuration,
        );
        $privilegedAccessConfiguration = (new PrivilegedAccessConfigurationFactory())->create(
            $loadedEnvironment->variables(),
        );
        $securityAuditConfiguration = (new SecurityAuditConfigurationFactory())->create(
            $loadedEnvironment->variables(),
            $configuration,
        );
        $accountStateConfiguration = (new AccountStateConfigurationFactory())->create(
            $loadedEnvironment->variables(),
        );
        $peopleProfilesConfiguration = (new PeopleProfilesConfigurationFactory())->create(
            $loadedEnvironment->variables(),
        );

        $registry = new ModuleRegistry([
            new CoreFoundationModule($configuration, $loadedEnvironment->variables(), $runtimeEnvironment),
            new ApplicationServicesFoundationModule(),
            new ObservabilityFoundationModule($loggingConfiguration),
            new DatabaseFoundationModule($databaseConfiguration),
            new IdentityFoundationModule(),
            new TenancyFoundationModule(),
            new SchemaFoundationModule($this->projectRoot),
            new BackgroundExecutionFoundationModule($backgroundConfiguration),
            new PresentationFoundationModule($this->projectRoot),
            new HttpFoundationModule($this->projectRoot),
            new GeographyReferenceModule($this->projectRoot),
            new SecurityWebModule($identityAccessConfiguration),
            new IdentityAccessModule($identityAccessConfiguration),
            new IdentitySessionsModule($identitySessionConfiguration, $identityMultiFactorConfiguration),
            new IdentitySecurityNotificationsModule(
                $securityNotificationConfiguration,
                $identityAccessConfiguration,
            ),
            new IdentityRecoveryModule(
                $identityRecoveryConfiguration,
                $identityAccessConfiguration,
            ),
            new IdentityMultiFactorModule(),
            new SecurityAuthorizationModule(),
            new TenancyContextModule($this->projectRoot),
            new SecurityPrivilegedAccessModule($privilegedAccessConfiguration),
            new SecurityAuditModule($securityAuditConfiguration),
            new IdentityAccountStateModule($accountStateConfiguration),
            new PeopleProfilesModule($peopleProfilesConfiguration),
            new ApplicationHttpModule($this->projectRoot),
            new ConsoleFoundationModule(),
        ]);
        $builder = new ContainerBuilder();
        $compilation = $registry->compile($builder);

        return $builder->build($compilation->moduleDependencies);
    }
}
