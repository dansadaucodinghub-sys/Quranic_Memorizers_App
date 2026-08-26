<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\MySql;

use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\Database\DatabaseConfiguration;
use Qmdb\Shared\Configuration\Database\DatabaseTlsMode;
use Qmdb\Shared\Configuration\Database\MySqlDsnBuilder;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlServerVerifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlSessionInitializer;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlSessionVerifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\NativePdoConnector;
use Qmdb\Shared\Schema\Configuration\SchemaConfiguration;
use Qmdb\Shared\Schema\Connection\MySqlSchemaConnectionProvider;
use Qmdb\Shared\Security\Secrets\EnvironmentSecretsProvider;

abstract class SchemaMySqlIntegrationTestCase extends TestCase
{
    /** @var array<string, string> */
    private array $environment = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (
            [
            'QMDB_TEST_DB_HOST',
            'QMDB_TEST_DB_PORT',
            'QMDB_TEST_DB_NAME',
            'QMDB_TEST_DB_SCHEMA_USERNAME',
            'QMDB_TEST_DB_SCHEMA_PASSWORD',
            'QMDB_TEST_DB_TLS_MODE',
            ] as $name
        ) {
            $value = getenv($name);
            if (!is_string($value) || trim($value) === '') {
                self::markTestSkipped('Dedicated QMDB MySQL schema test environment is not configured.');
            }
            $this->environment[$name] = $value;
        }
        $ca = getenv('QMDB_TEST_DB_TLS_CA_FILE');
        $this->environment['QMDB_TEST_DB_TLS_CA_FILE'] = is_string($ca) ? $ca : '';
    }

    final protected function schemaConfiguration(int $lockTimeout = 1): SchemaConfiguration
    {
        $database = new DatabaseConfiguration(
            $this->environment['QMDB_TEST_DB_HOST'],
            (int) $this->environment['QMDB_TEST_DB_PORT'],
            $this->environment['QMDB_TEST_DB_NAME'],
            $this->environment['QMDB_TEST_DB_SCHEMA_USERNAME'],
            DatabaseTlsMode::parse($this->environment['QMDB_TEST_DB_TLS_MODE']),
            trim($this->environment['QMDB_TEST_DB_TLS_CA_FILE']) === ''
                ? null
                : $this->environment['QMDB_TEST_DB_TLS_CA_FILE'],
            5,
            new TransactionRetryPolicy(1, 0, 0),
            ApplicationEnvironment::TEST,
        );

        return new SchemaConfiguration($database, $lockTimeout, ApplicationEnvironment::TEST);
    }

    final protected function schemaProvider(
        ?SchemaConfiguration $configuration = null,
    ): MySqlSchemaConnectionProvider {
        $configuration ??= $this->schemaConfiguration();

        return new MySqlSchemaConnectionProvider(
            $configuration,
            new MySqlDsnBuilder(),
            new EnvironmentSecretsProvider(new EnvironmentVariables([
                'DB_SCHEMA_PASSWORD' => $this->environment['QMDB_TEST_DB_SCHEMA_PASSWORD'],
            ])),
            new NativePdoConnector(),
            new MySqlSessionInitializer(),
            new MySqlSessionVerifier(),
            new MySqlServerVerifier($configuration->database()),
        );
    }
}
