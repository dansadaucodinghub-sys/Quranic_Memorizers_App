<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction;

use Qmdb\Shared\Database\Transaction\TransactionOptions;

interface MySqlTransactionDriver
{
    public function begin(TransactionOptions $options): void;
    public function commit(): void;
    public function rollBack(): void;
    public function createSavepoint(string $name): void;
    public function releaseSavepoint(string $name): void;
    public function rollBackToSavepoint(string $name): void;
}
