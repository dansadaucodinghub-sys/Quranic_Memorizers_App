<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\MySql;

use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\Database\DatabaseConfiguration;
use Qmdb\Shared\Configuration\Database\DatabaseTlsMode;
use Qmdb\Shared\Configuration\Database\MySqlDsnBuilder;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlConnectionFactory;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlConnectionProvider;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlServerVerifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlSessionInitializer;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlSessionVerifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\NativePdoConnector;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\MySqlRetryableTransactionFailureClassifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\MySqlTransactionManager;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\NativeSleeper;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\PdoMySqlTransactionDriver;
use Qmdb\Shared\Security\Secrets\EnvironmentSecretsProvider;

abstract class MySqlIntegrationTestCase extends TestCase
{
    /** @var array<string, string> */
    private array $testEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (
            [
                'QMDB_TEST_DB_HOST',
                'QMDB_TEST_DB_PORT',
                'QMDB_TEST_DB_NAME',
                'QMDB_TEST_DB_USERNAME',
                'QMDB_TEST_DB_PASSWORD',
                'QMDB_TEST_DB_TLS_MODE',
            ] as $name
        ) {
            $value = getenv($name);
            if (!is_string($value) || trim($value) === '') {
                self::markTestSkipped('Dedicated QMDB MySQL test environment is not configured.');
            }
            $this->testEnvironment[$name] = $value;
        }
        $ca = getenv('QMDB_TEST_DB_TLS_CA_FILE');
        $this->testEnvironment['QMDB_TEST_DB_TLS_CA_FILE'] = is_string($ca) ? $ca : '';
    }

    /** @param array<string, string|int> $overrides */
    final protected function configuration(array $overrides = []): DatabaseConfiguration
    {
        $values = array_replace([
            'host' => $this->testEnvironment['QMDB_TEST_DB_HOST'],
            'port' => (int) $this->testEnvironment['QMDB_TEST_DB_PORT'],
            'database' => $this->testEnvironment['QMDB_TEST_DB_NAME'],
            'username' => $this->testEnvironment['QMDB_TEST_DB_USERNAME'],
            'tls_mode' => $this->testEnvironment['QMDB_TEST_DB_TLS_MODE'],
            'tls_ca_file' => $this->testEnvironment['QMDB_TEST_DB_TLS_CA_FILE'],
        ], $overrides);

        return new DatabaseConfiguration(
            (string) $values['host'],
            (int) $values['port'],
            (string) $values['database'],
            (string) $values['username'],
            DatabaseTlsMode::parse((string) $values['tls_mode']),
            trim((string) $values['tls_ca_file']) === '' ? null : (string) $values['tls_ca_file'],
            5,
            new TransactionRetryPolicy(3, 1, 10),
            ApplicationEnvironment::TEST,
        );
    }

    final protected function provider(
        ?DatabaseConfiguration $configuration = null,
        ?string $password = null,
    ): MySqlConnectionProvider {
        $configuration ??= $this->configuration();
        $password ??= $this->testEnvironment['QMDB_TEST_DB_PASSWORD'];
        $factory = new MySqlConnectionFactory(
            $configuration,
            new MySqlDsnBuilder(),
            new EnvironmentSecretsProvider(new EnvironmentVariables(['DB_PASSWORD' => $password])),
            new NativePdoConnector(),
        );

        return new MySqlConnectionProvider(
            $factory,
            new MySqlSessionInitializer(),
            new MySqlSessionVerifier(),
            new MySqlServerVerifier($configuration),
        );
    }

    final protected function transactionManager(MySqlConnectionProvider $provider): MySqlTransactionManager
    {
        return new MySqlTransactionManager(
            new PdoMySqlTransactionDriver($provider),
            new MySqlRetryableTransactionFailureClassifier(),
            new DeterministicRetryDelayStrategy(0),
            new RecordingSleeper(),
            TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 0, 0)),
        );
    }
}
