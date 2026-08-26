<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\MySql;

use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\MySqlTransactionDriver;

final class RecordingTransactionDriver implements MySqlTransactionDriver
{
    /** @var list<string> */
    public array $events = [];

    public function begin(TransactionOptions $options): void
    {
        $this->events[] = 'begin:' . ($options->isReadOnly() ? 'read-only' : 'read-write');
    }

    public function commit(): void
    {
        $this->events[] = 'commit';
    }
    public function rollBack(): void
    {
        $this->events[] = 'rollback';
    }
    public function createSavepoint(string $name): void
    {
        $this->events[] = 'savepoint:' . $name;
    }
    public function releaseSavepoint(string $name): void
    {
        $this->events[] = 'release:' . $name;
    }
    public function rollBackToSavepoint(string $name): void
    {
        $this->events[] = 'rollback-to:' . $name;
    }
}
