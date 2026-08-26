<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\MySql;

use Qmdb\Shared\Database\Transaction\RetryDelayStrategy;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;

final readonly class DeterministicRetryDelayStrategy implements RetryDelayStrategy
{
    public function __construct(private int $delay = 7)
    {
    }

    public function delayMilliseconds(int $failedAttempt, TransactionRetryPolicy $policy): int
    {
        return min($this->delay, $policy->maximumDelayMilliseconds());
    }
}
