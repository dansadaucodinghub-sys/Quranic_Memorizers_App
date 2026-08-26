<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Infrastructure\Persistence\MySql;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\Database\DatabaseConfiguration;
use Qmdb\Shared\Configuration\Database\DatabaseTlsMode;
use Qmdb\Shared\Configuration\Database\MySqlDsnBuilder;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlConnectionFactory;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Exception\DatabaseConnectionException;
use Qmdb\Shared\Security\Secrets\EnvironmentSecretsProvider;
use Qmdb\Tests\Support\MySql\RecordingPdoConnector;

final class MySqlConnectionFactoryTest extends TestCase
{
    public function testFactoryAppliesSecurePdoAttributesAtTheConstructionBoundary(): void
    {
        $connector = new RecordingPdoConnector();
        $factory = $this->factory($connector, 'test-only-password');

        $connection = $factory->create();

        self::assertInstanceOf(PDO::class, $connection);
        self::assertSame(1, $connector->calls);
        self::assertSame('qmdb_app', $connector->username);
        self::assertSame('test-only-password', $connector->password);
        self::assertFalse($connector->options[PDO::ATTR_EMULATE_PREPARES]);
        self::assertFalse($connector->options[PDO::ATTR_PERSISTENT]);
        self::assertFalse($connector->options[\Pdo\Mysql::ATTR_MULTI_STATEMENTS]);
        self::assertFalse($connector->options[PDO::ATTR_STRINGIFY_FETCHES]);
        self::assertSame(PDO::FETCH_ASSOC, $connector->options[PDO::ATTR_DEFAULT_FETCH_MODE]);
        self::assertSame(5, $connector->options[PDO::ATTR_TIMEOUT]);
    }

    public function testConnectionFailureIsWrappedWithoutCredentialOrDsnDisclosure(): void
    {
        $connector = new RecordingPdoConnector(new PDOException('driver leaked test-only-password'));

        try {
            $this->factory($connector, 'test-only-password')->create();
            self::fail('Connection failure was not wrapped.');
        } catch (DatabaseConnectionException $exception) {
            self::assertInstanceOf(PDOException::class, $exception->getPrevious());
            self::assertStringNotContainsString('test-only-password', $exception->getMessage());
            self::assertStringNotContainsString('mysql:', $exception->getMessage());
            self::assertSame('DB_CONNECTION_FAILED', $exception->safeCode());
        }
    }

    private function factory(RecordingPdoConnector $connector, string $password): MySqlConnectionFactory
    {
        return new MySqlConnectionFactory(
            new DatabaseConfiguration(
                'localhost',
                3306,
                'qmdb',
                'qmdb_app',
                DatabaseTlsMode::DISABLED,
                null,
                5,
                new TransactionRetryPolicy(3, 25, 250),
                ApplicationEnvironment::TEST,
            ),
            new MySqlDsnBuilder(),
            new EnvironmentSecretsProvider(new EnvironmentVariables(['DB_PASSWORD' => $password])),
            $connector,
        );
    }
}
