<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Shared\Configuration\Database\DatabaseConfiguration;
use Qmdb\Shared\Configuration\Database\MySqlDsnBuilder;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Health\DatabaseHealthCheck;
use Qmdb\Shared\Database\Transaction\RetryDelayStrategy;
use Qmdb\Shared\Database\Transaction\Sleeper;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlConnectionFactory;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlConnectionProvider;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlServerVerifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlSessionInitializer;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlSessionVerifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\NativePdoConnector;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\PdoConnector;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Health\MySqlDatabaseHealthCheck;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\ExponentialJitterRetryDelayStrategy;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\MySqlRetryableTransactionFailureClassifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\MySqlTransactionDriver;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\MySqlTransactionManager;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\NativeSleeper;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\PdoMySqlTransactionDriver;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Security\Secrets\SecretsProvider;

final readonly class DatabaseFoundationModule implements Module
{
    private const ID = 'foundation.database';

    public function __construct(private DatabaseConfiguration $configuration)
    {
    }

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [new ModuleId('foundation.core')];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(DatabaseConfiguration::class, self::ID, $this->configuration));
        $this->registerConnectionServices($context);
        $this->registerTransactionServices($context);
        $context->service(ServiceDefinition::factory(
            MySqlDatabaseHealthCheck::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MySqlDatabaseHealthCheck => new MySqlDatabaseHealthCheck(
                    ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                ),
            ),
        ));
        $context->alias(DatabaseHealthCheck::class, MySqlDatabaseHealthCheck::class);
    }

    private function registerConnectionServices(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            MySqlDsnBuilder::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MySqlDsnBuilder => new MySqlDsnBuilder(),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            NativePdoConnector::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): NativePdoConnector => new NativePdoConnector(),
            ),
        ));
        $context->alias(PdoConnector::class, NativePdoConnector::class);
        $context->service(ServiceDefinition::factory(
            MySqlConnectionFactory::class,
            self::ID,
            [DatabaseConfiguration::class, MySqlDsnBuilder::class, SecretsProvider::class, PdoConnector::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MySqlConnectionFactory => new MySqlConnectionFactory(
                    ServiceReference::get($resolver, DatabaseConfiguration::class),
                    ServiceReference::get($resolver, MySqlDsnBuilder::class),
                    ServiceReference::get($resolver, SecretsProvider::class),
                    ServiceReference::get($resolver, PdoConnector::class),
                ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlSessionInitializer::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MySqlSessionInitializer => new MySqlSessionInitializer(),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlSessionVerifier::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MySqlSessionVerifier => new MySqlSessionVerifier(),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlServerVerifier::class,
            self::ID,
            [DatabaseConfiguration::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MySqlServerVerifier => new MySqlServerVerifier(
                    ServiceReference::get($resolver, DatabaseConfiguration::class),
                ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlConnectionProvider::class,
            self::ID,
            [
                MySqlConnectionFactory::class,
                MySqlSessionInitializer::class,
                MySqlSessionVerifier::class,
                MySqlServerVerifier::class,
            ],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MySqlConnectionProvider => new MySqlConnectionProvider(
                    ServiceReference::get($resolver, MySqlConnectionFactory::class),
                    ServiceReference::get($resolver, MySqlSessionInitializer::class),
                    ServiceReference::get($resolver, MySqlSessionVerifier::class),
                    ServiceReference::get($resolver, MySqlServerVerifier::class),
                ),
            ),
        ));
        $context->alias(DatabaseConnectionProvider::class, MySqlConnectionProvider::class);
    }

    private function registerTransactionServices(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            PdoMySqlTransactionDriver::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): PdoMySqlTransactionDriver => new PdoMySqlTransactionDriver(
                    ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                ),
            ),
        ));
        $context->alias(MySqlTransactionDriver::class, PdoMySqlTransactionDriver::class);
        $context->service(ServiceDefinition::factory(
            MySqlRetryableTransactionFailureClassifier::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MySqlRetryableTransactionFailureClassifier =>
                    new MySqlRetryableTransactionFailureClassifier(),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            ExponentialJitterRetryDelayStrategy::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): ExponentialJitterRetryDelayStrategy =>
                    new ExponentialJitterRetryDelayStrategy(),
            ),
        ));
        $context->alias(RetryDelayStrategy::class, ExponentialJitterRetryDelayStrategy::class);
        $context->service(ServiceDefinition::factory(
            NativeSleeper::class,
            self::ID,
            [],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): NativeSleeper => new NativeSleeper()),
        ));
        $context->alias(Sleeper::class, NativeSleeper::class);
        $context->service(ServiceDefinition::factory(
            MySqlTransactionManager::class,
            self::ID,
            [
                MySqlTransactionDriver::class,
                MySqlRetryableTransactionFailureClassifier::class,
                RetryDelayStrategy::class,
                Sleeper::class,
            ],
            new ClosureServiceFactory(function (DependencyResolver $resolver): MySqlTransactionManager {
                return new MySqlTransactionManager(
                    ServiceReference::get($resolver, MySqlTransactionDriver::class),
                    ServiceReference::get($resolver, MySqlRetryableTransactionFailureClassifier::class),
                    ServiceReference::get($resolver, RetryDelayStrategy::class),
                    ServiceReference::get($resolver, Sleeper::class),
                    TransactionOptions::readWrite(
                        retryPolicy: $this->configuration->deadlockRetryPolicy(),
                    ),
                );
            }),
        ));
        $context->alias(TransactionManager::class, MySqlTransactionManager::class);
    }
}
