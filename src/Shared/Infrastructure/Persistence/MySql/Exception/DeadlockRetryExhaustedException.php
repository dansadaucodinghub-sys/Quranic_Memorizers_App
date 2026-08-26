<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Exception;

use Throwable;

final class DeadlockRetryExhaustedException extends TransactionException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Transaction retry attempts were exhausted.', $previous);
    }
}
