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
use Qmdb\Bootstrap\Module\CoreFoundationModule;
use Qmdb\Bootstrap\Module\DatabaseFoundationModule;
use Qmdb\Bootstrap\Module\HttpFoundationModule;
use Qmdb\Bootstrap\Module\IdentityAccessModule;
use Qmdb\Bootstrap\Module\IdentityFoundationModule;
use Qmdb\Bootstrap\Module\ObservabilityFoundationModule;
use Qmdb\Bootstrap\Module\PresentationFoundationModule;
use Qmdb\Bootstrap\Module\SchemaFoundationModule;
use Qmdb\Bootstrap\Module\SecurityWebModule;
use Qmdb\Bootstrap\Module\TenancyFoundationModule;
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
            'foundation.application',
            'foundation.database',
            'foundation.observability',
            'foundation.presentation',
            'foundation.schema',
            'foundation.http',
            'identity.accounts',
            'security.web',
            'identity.access',
            'application.http',
            'foundation.background',
            'foundation.console',
            'tenancy.workspaces',
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
        self::assertSame('QMDB-P2-B02', $result->currentBatch());
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
            'MAILER_DSN' => 'null://null',
        ]);
        $configuration = (new ApplicationConfigurationFactory())->create(
            $variables,
            ConfigurationSource::PROCESS,
        );
        $identityAccess = (new IdentityAccessConfigurationFactory())->create($variables, $configuration);
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
            new SecurityWebModule($identityAccess),
            new IdentityAccessModule($identityAccess),
            new ApplicationHttpModule(dirname(__DIR__, 3)),
            new ConsoleFoundationModule(),
        ]);
        $builder = new ContainerBuilder();
        $compilation = $registry->compile($builder);

        return [$registry, $builder->build($compilation->moduleDependencies)];
    }
}
