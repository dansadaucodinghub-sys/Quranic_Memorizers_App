<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDOException;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

final class MySqlReadOnlyTransactionIntegrationTest extends MySqlIntegrationTestCase
{
    public function testReadOnlyTransactionRejectsWriteToNonTemporaryTable(): void
    {
        $provider = $this->provider();
        $connection = $provider->connection();
        $table = 'qmdb_b05_readonly_' . bin2hex(random_bytes(5));
        self::assertMatchesRegularExpression('/\Aqmdb_b05_readonly_[a-f0-9]{10}\z/', $table);
        $connection->exec("CREATE TABLE {$table} (id INT PRIMARY KEY) ENGINE=InnoDB");

        try {
            $this->expectException(PDOException::class);
            $this->transactionManager($provider)->transactional(
                static function () use ($connection, $table): void {
                    $connection->exec("INSERT INTO {$table} (id) VALUES (1)");
                },
                TransactionOptions::readOnly(),
            );
        } finally {
            $connection->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
