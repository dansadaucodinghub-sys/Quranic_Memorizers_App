<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Configuration\Database;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\Database\DatabaseConfiguration;
use Qmdb\Shared\Configuration\Database\DatabaseConfigurationException;
use Qmdb\Shared\Configuration\Database\DatabaseTlsMode;
use Qmdb\Shared\Configuration\Database\MySqlDsnBuilder;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;

final class DatabaseConfigurationTest extends TestCase
{
    /** @return iterable<string, array{string, int, string, string}> */
    public static function invalidIdentityValues(): iterable
    {
        yield 'empty host' => ['', 3306, 'qmdb', 'qmdb_app'];
        yield 'dsn separator host' => ['localhost;unix_socket=x', 3306, 'qmdb', 'qmdb_app'];
        yield 'nul host' => ["local\0host", 3306, 'qmdb', 'qmdb_app'];
        yield 'control host' => ["local\nhost", 3306, 'qmdb', 'qmdb_app'];
        yield 'port below range' => ['localhost', 0, 'qmdb', 'qmdb_app'];
        yield 'port above range' => ['localhost', 65536, 'qmdb', 'qmdb_app'];
        yield 'unsafe database name' => ['localhost', 3306, 'qmdb;drop', 'qmdb_app'];
        yield 'empty username' => ['localhost', 3306, 'qmdb', ''];
        yield 'root username' => ['localhost', 3306, 'qmdb', 'root'];
        yield 'root account expression' => ['localhost', 3306, 'qmdb', 'root@localhost'];
    }

    #[DataProvider('invalidIdentityValues')]
    public function testUnsafeIdentityConfigurationIsRejected(
        string $host,
        int $port,
        string $database,
        string $username,
    ): void {
        $this->expectException(DatabaseConfigurationException::class);
        $this->configuration($host, $port, $database, $username);
    }

    public function testInvalidTlsModeIsRejected(): void
    {
        $this->expectException(DatabaseConfigurationException::class);
        DatabaseTlsMode::parse('prefer');
    }

    public function testDisabledTlsIsRejectedInProductionLikeEnvironments(): void
    {
        foreach ([ApplicationEnvironment::STAGING, ApplicationEnvironment::PRODUCTION] as $environment) {
            try {
                $this->configuration(environment: $environment);
                self::fail('Production-like disabled TLS was accepted.');
            } catch (DatabaseConfigurationException $exception) {
                self::assertSame('DB_CONFIG_TLS_REQUIRED', $exception->safeCode());
            }
        }
    }

    public function testVerifiedTlsRequiresReadableCaFile(): void
    {
        $this->expectException(DatabaseConfigurationException::class);
        $this->configuration(tlsMode: DatabaseTlsMode::VERIFY_SERVER, tlsCaFile: 'missing-ca.pem');
    }

    public function testSafeArrayAndDsnContainNoCredentialSecret(): void
    {
        $configuration = $this->configuration();
        $safe = $configuration->toSafeArray();
        $dsn = (new MySqlDsnBuilder())->build($configuration);

        self::assertArrayNotHasKey('password', $safe);
        self::assertStringNotContainsString('password', strtolower($dsn));
        self::assertStringNotContainsString('qmdb_app', $dsn);
        self::assertSame('mysql:host=localhost;port=3306;dbname=qmdb;charset=utf8mb4', $dsn);
    }

    public function testInvalidTimeoutAndRetryParametersAreRejected(): void
    {
        $this->expectException(DatabaseConfigurationException::class);
        $this->configuration(timeout: 0);
    }

    private function configuration(
        string $host = 'localhost',
        int $port = 3306,
        string $database = 'qmdb',
        string $username = 'qmdb_app',
        DatabaseTlsMode $tlsMode = DatabaseTlsMode::DISABLED,
        ?string $tlsCaFile = null,
        int $timeout = 5,
        ApplicationEnvironment $environment = ApplicationEnvironment::TEST,
    ): DatabaseConfiguration {
        return new DatabaseConfiguration(
            $host,
            $port,
            $database,
            $username,
            $tlsMode,
            $tlsCaFile,
            $timeout,
            new TransactionRetryPolicy(3, 25, 250),
            $environment,
        );
    }
}
