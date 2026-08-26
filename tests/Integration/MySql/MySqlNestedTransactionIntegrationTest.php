<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;
use RuntimeException;

final class MySqlNestedTransactionIntegrationTest extends MySqlIntegrationTestCase
{
    public function testNestedRollbackPreservesOuterWorkAndOuterRollbackRemovesNestedWork(): void
    {
        $provider = $this->provider();
        $connection = $provider->connection();
        $connection->exec('CREATE TEMPORARY TABLE qmdb_b05_nested (id INT PRIMARY KEY) ENGINE=InnoDB');
        $manager = $this->transactionManager($provider);

        $manager->transactional(function () use ($manager, $connection): void {
            $connection->exec('INSERT INTO qmdb_b05_nested (id) VALUES (1)');
            try {
                $manager->transactional(static function () use ($connection): never {
                    $connection->exec('INSERT INTO qmdb_b05_nested (id) VALUES (2)');
                    throw new RuntimeException('nested rollback');
                });
            } catch (RuntimeException) {
            }
        });
        try {
            $manager->transactional(function () use ($manager, $connection): never {
                $manager->transactional(static function () use ($connection): void {
                    $connection->exec('INSERT INTO qmdb_b05_nested (id) VALUES (3)');
                });
                throw new RuntimeException('outer rollback');
            });
        } catch (RuntimeException) {
        }

        $count = $connection->query('SELECT COUNT(*) FROM qmdb_b05_nested');
        self::assertNotFalse($count);
        self::assertSame(1, (int) $count->fetchColumn());
    }
}
