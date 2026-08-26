<?php

declare(strict_types=1);

namespace Qmdb\Shared\Database\Transaction;

interface RetryDelayStrategy
{
    public function delayMilliseconds(int $failedAttempt, TransactionRetryPolicy $policy): int;
}
