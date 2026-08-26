<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Closure;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\Database\DatabaseConfiguration;
use Qmdb\Shared\Configuration\Database\MySqlDsnBuilder;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlServerVerifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlSessionInitializer;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlSessionVerifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\PdoConnector;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Schema\Checksum\CanonicalChecksum;
use Qmdb\Shared\Schema\Configuration\SchemaConfiguration;
use Qmdb\Shared\Schema\Configuration\SchemaConfigurationFactory;
use Qmdb\Shared\Schema\Connection\MySqlSchemaConnectionProvider;
use Qmdb\Shared\Schema\Connection\RuntimeSchemaConnectionProvider;
use Qmdb\Shared\Schema\Connection\SchemaConnectionProvider;
use Qmdb\Shared\Schema\Console\SchemaConsoleApplication;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Qmdb\Shared\Schema\Lock\SchemaLockManager;
use Qmdb\Shared\Schema\Lock\SchemaMutationLock;
use Qmdb\Shared\Schema\Metadata\RuntimeSchemaMetadataVerifier;
use Qmdb\Shared\Schema\Metadata\SchemaMetadataInstaller;
use Qmdb\Shared\Schema\Metadata\SchemaMetadataInstallation;
use Qmdb\Shared\Schema\Metadata\SchemaMetadataVerifier;
use Qmdb\Shared\Schema\Migration\MigrationChecksum;
use Qmdb\Shared\Schema\Migration\MigrationPlanner;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Runner\MigrationRollbackService;
use Qmdb\Shared\Schema\Runner\MigrationRunner;
use Qmdb\Shared\Schema\Runner\SeedRunner;
use Qmdb\Shared\Schema\Seed\SeedChecksum;
use Qmdb\Shared\Schema\Seed\SeedPlanner;
use Qmdb\Shared\Schema\Seed\SeedRegistry;
use Qmdb\Shared\Schema\State\MySqlRuntimeSchemaStateReader;
use Qmdb\Shared\Schema\State\MySqlSchemaStateRepository;
use Qmdb\Shared\Schema\State\SchemaStateRepository;
use Qmdb\Shared\Schema\Status\SchemaStatusService;
use Qmdb\Shared\Security\Secrets\SecretsProvider;
use RuntimeException;

final readonly class SchemaFoundationModule implements Module
{
    private const ID = 'foundation.schema';

    public function __construct(private string $projectRoot)
    {
    }

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('foundation.core'),
            new ModuleId('foundation.application'),
            new ModuleId('foundation.database'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $this->registerConfigurationAndConnections($context);
        $this->registerRegistriesAndChecksums($context);
        $this->registerMetadataAndState($context);
        $this->registerOperations($context);
    }

    private function registerConfigurationAndConnections(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            SchemaConfiguration::class,
            self::ID,
            [EnvironmentVariables::class, DatabaseConfiguration::class, ApplicationConfiguration::class],
            new ClosureServiceFactory(static function (DependencyResolver $resolver): SchemaConfiguration {
                return (new SchemaConfigurationFactory())->create(
                    ServiceReference::get($resolver, EnvironmentVariables::class),
                    ServiceReference::get($resolver, DatabaseConfiguration::class),
                    ServiceReference::get($resolver, ApplicationConfiguration::class)->environment(),
                );
            }),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlSchemaConnectionProvider::class,
            self::ID,
            [
                SchemaConfiguration::class,
                MySqlDsnBuilder::class,
                SecretsProvider::class,
                PdoConnector::class,
                MySqlSessionInitializer::class,
                MySqlSessionVerifier::class,
            ],
            new ClosureServiceFactory(static function (DependencyResolver $resolver): MySqlSchemaConnectionProvider {
                $configuration = ServiceReference::get($resolver, SchemaConfiguration::class);

                return new MySqlSchemaConnectionProvider(
                    $configuration,
                    ServiceReference::get($resolver, MySqlDsnBuilder::class),
                    ServiceReference::get($resolver, SecretsProvider::class),
                    ServiceReference::get($resolver, PdoConnector::class),
                    ServiceReference::get($resolver, MySqlSessionInitializer::class),
                    ServiceReference::get($resolver, MySqlSessionVerifier::class),
                    new MySqlServerVerifier($configuration->database()),
                );
            }),
        ));
        $context->alias(SchemaConnectionProvider::class, MySqlSchemaConnectionProvider::class);
        $context->service(ServiceDefinition::factory(
            RuntimeSchemaConnectionProvider::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): RuntimeSchemaConnectionProvider =>
                    new RuntimeSchemaConnectionProvider(
                        ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                    ),
            ),
        ));
    }

    private function registerRegistriesAndChecksums(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            MigrationRegistry::class,
            self::ID,
            [],
            new ClosureServiceFactory(function (DependencyResolver $resolver): MigrationRegistry {
                $factory = require $this->projectRoot . '/database/migrations.php';
                if (!$factory instanceof Closure) {
                    throw new RuntimeException('Migration manifest must return a factory closure.');
                }

                $registry = $factory();
                if (!$registry instanceof MigrationRegistry) {
                    throw new RuntimeException('Migration manifest returned an invalid registry.');
                }

                return $registry;
            }),
        ));
        $context->service(ServiceDefinition::factory(
            SeedRegistry::class,
            self::ID,
            [],
            new ClosureServiceFactory(function (DependencyResolver $resolver): SeedRegistry {
                $factory = require $this->projectRoot . '/database/seeds.php';
                if (!$factory instanceof Closure) {
                    throw new RuntimeException('Seed manifest must return a factory closure.');
                }

                $registry = $factory();
                if (!$registry instanceof SeedRegistry) {
                    throw new RuntimeException('Seed manifest returned an invalid registry.');
                }

                return $registry;
            }),
        ));
        $context->service(ServiceDefinition::instance(CanonicalChecksum::class, self::ID, new CanonicalChecksum()));
        $context->service(ServiceDefinition::factory(
            MigrationChecksum::class,
            self::ID,
            [CanonicalChecksum::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MigrationChecksum => new MigrationChecksum(
                    ServiceReference::get($resolver, CanonicalChecksum::class),
                ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            SeedChecksum::class,
            self::ID,
            [CanonicalChecksum::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SeedChecksum => new SeedChecksum(
                    ServiceReference::get($resolver, CanonicalChecksum::class),
                ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            MigrationPlanner::class,
            self::ID,
            [MigrationChecksum::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MigrationPlanner => new MigrationPlanner(
                    ServiceReference::get($resolver, MigrationChecksum::class),
                ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            SeedPlanner::class,
            self::ID,
            [SeedChecksum::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SeedPlanner => new SeedPlanner(
                    ServiceReference::get($resolver, SeedChecksum::class),
                ),
            ),
        ));
    }

    private function registerMetadataAndState(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            SchemaLockManager::class,
            self::ID,
            [SchemaConnectionProvider::class, SchemaConfiguration::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SchemaLockManager => new SchemaLockManager(
                    ServiceReference::get($resolver, SchemaConnectionProvider::class),
                    ServiceReference::get($resolver, SchemaConfiguration::class),
                ),
            ),
        ));
        $context->alias(SchemaMutationLock::class, SchemaLockManager::class);
        $context->service(ServiceDefinition::factory(
            SchemaMetadataVerifier::class,
            self::ID,
            [SchemaConnectionProvider::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SchemaMetadataVerifier => new SchemaMetadataVerifier(
                    ServiceReference::get($resolver, SchemaConnectionProvider::class),
                ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            SchemaMetadataInstaller::class,
            self::ID,
            [SchemaConnectionProvider::class, SchemaMutationLock::class, SchemaMetadataVerifier::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SchemaMetadataInstaller => new SchemaMetadataInstaller(
                    ServiceReference::get($resolver, SchemaConnectionProvider::class),
                    ServiceReference::get($resolver, SchemaMutationLock::class),
                    ServiceReference::get($resolver, SchemaMetadataVerifier::class),
                ),
            ),
        ));
        $context->alias(SchemaMetadataInstallation::class, SchemaMetadataInstaller::class);
        $context->service(ServiceDefinition::factory(
            MySqlSchemaStateRepository::class,
            self::ID,
            [SchemaConnectionProvider::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MySqlSchemaStateRepository =>
                    new MySqlSchemaStateRepository(
                        ServiceReference::get($resolver, SchemaConnectionProvider::class),
                    ),
            ),
        ));
        $context->alias(SchemaStateRepository::class, MySqlSchemaStateRepository::class);
        $context->service(ServiceDefinition::factory(
            RuntimeSchemaMetadataVerifier::class,
            self::ID,
            [RuntimeSchemaConnectionProvider::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): RuntimeSchemaMetadataVerifier =>
                    new RuntimeSchemaMetadataVerifier(
                        ServiceReference::get($resolver, RuntimeSchemaConnectionProvider::class),
                    ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlRuntimeSchemaStateReader::class,
            self::ID,
            [RuntimeSchemaConnectionProvider::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MySqlRuntimeSchemaStateReader =>
                    new MySqlRuntimeSchemaStateReader(
                        ServiceReference::get($resolver, RuntimeSchemaConnectionProvider::class),
                    ),
            ),
        ));
    }

    private function registerOperations(ModuleRegistrationContext $context): void
    {
        $this->registerRunners($context);
        $context->service(ServiceDefinition::factory(
            SchemaHealthCheck::class,
            self::ID,
            [
                RuntimeSchemaMetadataVerifier::class,
                MySqlRuntimeSchemaStateReader::class,
                MigrationRegistry::class,
                MigrationPlanner::class,
            ],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SchemaHealthCheck => new SchemaHealthCheck(
                    ServiceReference::get($resolver, RuntimeSchemaMetadataVerifier::class),
                    ServiceReference::get($resolver, MySqlRuntimeSchemaStateReader::class),
                    ServiceReference::get($resolver, MigrationRegistry::class),
                    ServiceReference::get($resolver, MigrationPlanner::class),
                ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            SchemaStatusService::class,
            self::ID,
            [
                SchemaMetadataVerifier::class,
                SchemaStateRepository::class,
                MigrationRegistry::class,
                MigrationPlanner::class,
                SeedRegistry::class,
                SeedPlanner::class,
            ],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SchemaStatusService => new SchemaStatusService(
                    ServiceReference::get($resolver, SchemaMetadataVerifier::class),
                    ServiceReference::get($resolver, SchemaStateRepository::class),
                    ServiceReference::get($resolver, MigrationRegistry::class),
                    ServiceReference::get($resolver, MigrationPlanner::class),
                    ServiceReference::get($resolver, SeedRegistry::class),
                    ServiceReference::get($resolver, SeedPlanner::class),
                ),
            ),
        ));
        $schemaServices = [
            SchemaMetadataInstaller::class,
            SchemaStatusService::class,
            MigrationRunner::class,
            MigrationRollbackService::class,
            SeedRunner::class,
        ];
        $context->service(ServiceDefinition::factory(
            SchemaConsoleApplication::class,
            self::ID,
            $schemaServices,
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SchemaConsoleApplication => new SchemaConsoleApplication(
                    static fn (string $id): object => $resolver->get($id),
                ),
            ),
        ));
    }

    private function registerRunners(ModuleRegistrationContext $context): void
    {
        $common = [
            SchemaConnectionProvider::class,
            SchemaMutationLock::class,
            SchemaMetadataInstallation::class,
        ];
        $context->service(ServiceDefinition::factory(
            MigrationRunner::class,
            self::ID,
            [
                ...$common,
                MigrationRegistry::class,
                MigrationPlanner::class,
                MigrationChecksum::class,
                SchemaStateRepository::class,
            ],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MigrationRunner => new MigrationRunner(
                    ServiceReference::get($resolver, SchemaConnectionProvider::class),
                    ServiceReference::get($resolver, SchemaMutationLock::class),
                    ServiceReference::get($resolver, SchemaMetadataInstallation::class),
                    ServiceReference::get($resolver, MigrationRegistry::class),
                    ServiceReference::get($resolver, MigrationPlanner::class),
                    ServiceReference::get($resolver, MigrationChecksum::class),
                    ServiceReference::get($resolver, SchemaStateRepository::class),
                ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            MigrationRollbackService::class,
            self::ID,
            [
                ApplicationConfiguration::class,
                SchemaConnectionProvider::class,
                SchemaMutationLock::class,
                MigrationRegistry::class,
                MigrationChecksum::class,
                SchemaStateRepository::class,
            ],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MigrationRollbackService => new MigrationRollbackService(
                    ServiceReference::get($resolver, ApplicationConfiguration::class)->environment(),
                    ServiceReference::get($resolver, SchemaConnectionProvider::class),
                    ServiceReference::get($resolver, SchemaMutationLock::class),
                    ServiceReference::get($resolver, MigrationRegistry::class),
                    ServiceReference::get($resolver, MigrationChecksum::class),
                    ServiceReference::get($resolver, SchemaStateRepository::class),
                ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            SeedRunner::class,
            self::ID,
            [...$common, SeedRegistry::class, SeedPlanner::class, SeedChecksum::class, SchemaStateRepository::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SeedRunner => new SeedRunner(
                    ServiceReference::get($resolver, SchemaConnectionProvider::class),
                    ServiceReference::get($resolver, SchemaMutationLock::class),
                    ServiceReference::get($resolver, SchemaMetadataInstallation::class),
                    ServiceReference::get($resolver, SeedRegistry::class),
                    ServiceReference::get($resolver, SeedPlanner::class),
                    ServiceReference::get($resolver, SeedChecksum::class),
                    ServiceReference::get($resolver, SchemaStateRepository::class),
                ),
            ),
        ));
    }
}
