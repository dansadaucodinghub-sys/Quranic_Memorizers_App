<?php

declare(strict_types=1);

namespace Qmdb\Shared\Database\Transaction;

enum TransactionIsolation: string
{
    case READ_COMMITTED = 'READ COMMITTED';
    case REPEATABLE_READ = 'REPEATABLE READ';
    case SERIALIZABLE = 'SERIALIZABLE';
}
