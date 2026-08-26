<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use PDOException;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Exception\DatabaseConnectionException;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

final class MySqlConnectionIntegrationTest extends MySqlIntegrationTestCase
{
    public function testNativePreparedStatementConnectsAndReturnsExpectedValue(): void
    {
        $connection = $this->provider()->connection();
        $statement = $connection->prepare('SELECT :value AS value');
        $statement->execute(['value' => 17]);

        self::assertSame('17', $statement->fetchColumn());
        self::assertFalse($connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        self::assertFalse($connection->getAttribute(PDO::ATTR_PERSISTENT));
    }

    public function testMultipleStatementsAreRejected(): void
    {
        $this->expectException(PDOException::class);
        $this->provider()->connection()->prepare('SELECT 1; SELECT 2')->execute();
    }

    public function testWrongPasswordFailsWithSafeProjectException(): void
    {
        try {
            $this->provider(password: 'intentionally-wrong-test-password')->connection();
            self::fail('Wrong password was accepted.');
        } catch (DatabaseConnectionException $exception) {
            self::assertSame('Database connection could not be established.', $exception->getMessage());
            self::assertStringNotContainsString('intentionally-wrong', $exception->getMessage());
        }
    }

    public function testUnknownDatabaseFailsWithSafeProjectException(): void
    {
        $configuration = $this->configuration(['database' => 'qmdb_missing_b05_database']);

        $this->expectException(DatabaseConnectionException::class);
        $this->provider($configuration)->connection();
    }
}
