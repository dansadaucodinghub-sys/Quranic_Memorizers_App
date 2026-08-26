<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;
use RuntimeException;

final class MySqlTransactionIntegrationTest extends MySqlIntegrationTestCase
{
    public function testCommitRollbackAndRecoveryRemainUsable(): void
    {
        $provider = $this->provider();
        $connection = $provider->connection();
        $connection->exec('CREATE TEMPORARY TABLE qmdb_b05_probe (id INT PRIMARY KEY) ENGINE=InnoDB');
        $manager = $this->transactionManager($provider);

        $manager->transactional(static function () use ($connection): void {
            $connection->exec('INSERT INTO qmdb_b05_probe (id) VALUES (1)');
        });
        try {
            $manager->transactional(static function () use ($connection): never {
                $connection->exec('INSERT INTO qmdb_b05_probe (id) VALUES (2)');
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }
        $manager->transactional(static function () use ($connection): void {
            $connection->exec('INSERT INTO qmdb_b05_probe (id) VALUES (3)');
        });

        $count = $connection->query('SELECT COUNT(*) FROM qmdb_b05_probe');
        self::assertNotFalse($count);
        self::assertSame(2, (int) $count->fetchColumn());
    }
}
