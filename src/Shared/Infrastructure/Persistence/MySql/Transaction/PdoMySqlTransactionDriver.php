<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction;

use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionOptions;

final readonly class PdoMySqlTransactionDriver implements MySqlTransactionDriver
{
    public function __construct(private DatabaseConnectionProvider $connectionProvider)
    {
    }

    public function begin(TransactionOptions $options): void
    {
        $connection = $this->connectionProvider->connection();
        $connection->exec('SET TRANSACTION ISOLATION LEVEL ' . $options->isolation()->value);
        $connection->exec($options->isReadOnly() ? 'START TRANSACTION READ ONLY' : 'START TRANSACTION READ WRITE');
    }

    public function commit(): void
    {
        $this->connectionProvider->connection()->commit();
    }

    public function rollBack(): void
    {
        $this->connectionProvider->connection()->rollBack();
    }

    public function createSavepoint(string $name): void
    {
        $this->connectionProvider->connection()->exec('SAVEPOINT ' . $this->assertSavepoint($name));
    }

    public function releaseSavepoint(string $name): void
    {
        $this->connectionProvider->connection()->exec('RELEASE SAVEPOINT ' . $this->assertSavepoint($name));
    }

    public function rollBackToSavepoint(string $name): void
    {
        $this->connectionProvider->connection()->exec('ROLLBACK TO SAVEPOINT ' . $this->assertSavepoint($name));
    }

    private function assertSavepoint(string $name): string
    {
        if (preg_match('/\Aqmdb_sp_[1-9][0-9]*\z/', $name) !== 1) {
            throw new \LogicException('Application savepoint name is invalid.');
        }

        return $name;
    }
}
