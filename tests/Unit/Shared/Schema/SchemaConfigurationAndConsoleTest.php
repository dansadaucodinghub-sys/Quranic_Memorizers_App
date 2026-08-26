<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Schema;

use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\Shared\ExitCode;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\Database\DatabaseConfiguration;
use Qmdb\Shared\Configuration\Database\DatabaseConfigurationException;
use Qmdb\Shared\Configuration\Database\DatabaseTlsMode;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Schema\Configuration\SchemaConfiguration;
use Qmdb\Shared\Schema\Configuration\SchemaConfigurationFactory;
use Qmdb\Shared\Schema\Connection\SchemaConnectionProvider;
use Qmdb\Shared\Schema\Console\SchemaConsoleApplication;
use Qmdb\Shared\Schema\Lock\SchemaLockManager;
use Qmdb\Shared\Schema\Checksum\CanonicalChecksum;
use Qmdb\Shared\Schema\Exception\SchemaException;
use Qmdb\Shared\Schema\Migration\MigrationChecksum;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Runner\MigrationRollbackService;
use Qmdb\Shared\Schema\State\SchemaStateRepository;

final class SchemaConfigurationAndConsoleTest extends TestCase
{
    public function testSchemaConfigurationIsSeparateAndBounded(): void
    {
        $configuration = (new SchemaConfigurationFactory())->create(
            new EnvironmentVariables([
                'DB_SCHEMA_USERNAME' => 'qmdb_migrator',
                'DB_SCHEMA_LOCK_TIMEOUT_SECONDS' => '30',
            ]),
            $this->runtimeConfiguration(),
            ApplicationEnvironment::TEST,
        );

        self::assertSame('qmdb_migrator', $configuration->database()->username());
        self::assertSame(30, $configuration->lockTimeoutSeconds());
        self::assertStringNotContainsString('password', serialize($configuration->database()->toSafeArray()));
    }

    public function testRuntimeIdentityReuseIsRejected(): void
    {
        $this->expectException(DatabaseConfigurationException::class);
        (new SchemaConfigurationFactory())->create(
            new EnvironmentVariables(['DB_SCHEMA_USERNAME' => 'qmdb_app']),
            $this->runtimeConfiguration(),
            ApplicationEnvironment::TEST,
        );
    }

    public function testRootSchemaIdentityIsRejected(): void
    {
        $this->expectException(DatabaseConfigurationException::class);
        (new SchemaConfigurationFactory())->create(
            new EnvironmentVariables(['DB_SCHEMA_USERNAME' => 'root']),
            $this->runtimeConfiguration(),
            ApplicationEnvironment::TEST,
        );
    }

    public function testLockNameIsBoundedAndContainsNoRawDatabaseIdentity(): void
    {
        $schema = new SchemaConfiguration(
            new DatabaseConfiguration(
                'private-db.internal',
                3306,
                'private_database',
                'private_operator',
                DatabaseTlsMode::DISABLED,
                null,
                5,
                new TransactionRetryPolicy(1, 0, 0),
                ApplicationEnvironment::TEST,
            ),
            30,
            ApplicationEnvironment::TEST,
        );
        $provider = $this->createStub(SchemaConnectionProvider::class);
        $name = (new SchemaLockManager($provider, $schema))->lockName();

        self::assertLessThanOrEqual(64, strlen($name));
        self::assertStringStartsWith('qmdb:schema:', $name);
        self::assertStringNotContainsString('private-db', $name);
        self::assertStringNotContainsString('private_database', $name);
        self::assertStringNotContainsString('private_operator', $name);
    }

    public function testSchemaConsoleRejectsMalformedRollbackWithoutResolvingServices(): void
    {
        $console = new SchemaConsoleApplication(static function (string $id): object {
            self::fail('Malformed options must not resolve a schema service.');
        });
        $result = $console->run(['db:migrate:rollback', '--migration=20260825010101_test']);

        self::assertSame(ExitCode::INVALID_USAGE, $result->exitCode());
        self::assertStringContainsString('Usage:', $result->standardError());
        self::assertStringNotContainsString('DB_SCHEMA_PASSWORD', $result->standardError());
    }

    public function testSchemaConsolePublishesOnlyExplicitCommands(): void
    {
        $console = new SchemaConsoleApplication(static fn (string $id): object => new \stdClass());
        self::assertTrue($console->supports('db:migrate'));
        self::assertTrue($console->supports('db:seed:status'));
        self::assertFalse($console->supports('db:sql'));
        self::assertCount(8, $console->commands());
    }

    public function testProductionRollbackIsRejectedBeforeDatabaseAccess(): void
    {
        $provider = $this->createStub(SchemaConnectionProvider::class);
        $configuration = new SchemaConfiguration($this->runtimeConfiguration(), 30, ApplicationEnvironment::TEST);
        $service = new MigrationRollbackService(
            ApplicationEnvironment::PRODUCTION,
            $provider,
            new SchemaLockManager($provider, $configuration),
            new MigrationRegistry([]),
            new MigrationChecksum(new CanonicalChecksum()),
            $this->createStub(SchemaStateRepository::class),
        );

        try {
            $service->rollback('20260825010101_example', '20260825010101_example');
            self::fail('Production rollback was not rejected.');
        } catch (SchemaException $exception) {
            self::assertSame('ROLLBACK_ENVIRONMENT_PROHIBITED', $exception->safeCode());
        }
    }

    public function testRollbackRequiresExactConfirmationBeforeDatabaseAccess(): void
    {
        $provider = $this->createStub(SchemaConnectionProvider::class);
        $configuration = new SchemaConfiguration($this->runtimeConfiguration(), 30, ApplicationEnvironment::TEST);
        $service = new MigrationRollbackService(
            ApplicationEnvironment::TEST,
            $provider,
            new SchemaLockManager($provider, $configuration),
            new MigrationRegistry([]),
            new MigrationChecksum(new CanonicalChecksum()),
            $this->createStub(SchemaStateRepository::class),
        );

        try {
            $service->rollback('20260825010101_example', '20260825010102_other');
            self::fail('Mismatched rollback confirmation was not rejected.');
        } catch (SchemaException $exception) {
            self::assertSame('ROLLBACK_CONFIRMATION_MISMATCH', $exception->safeCode());
        }
    }

    private function runtimeConfiguration(): DatabaseConfiguration
    {
        return new DatabaseConfiguration(
            '127.0.0.1',
            3306,
            'qmdb_test',
            'qmdb_app',
            DatabaseTlsMode::DISABLED,
            null,
            5,
            new TransactionRetryPolicy(3, 1, 10),
            ApplicationEnvironment::TEST,
        );
    }
}
