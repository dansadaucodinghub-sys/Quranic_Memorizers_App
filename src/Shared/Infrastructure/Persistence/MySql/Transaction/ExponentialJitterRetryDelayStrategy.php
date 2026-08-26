<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction;

use InvalidArgumentException;
use Qmdb\Shared\Database\Transaction\RetryDelayStrategy;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;

final readonly class ExponentialJitterRetryDelayStrategy implements RetryDelayStrategy
{
    public function delayMilliseconds(int $failedAttempt, TransactionRetryPolicy $policy): int
    {
        if ($failedAttempt < 1) {
            throw new InvalidArgumentException('Failed attempt number must be positive.');
        }
        $exponent = min($failedAttempt - 1, 20);
        $base = min($policy->maximumDelayMilliseconds(), $policy->baseDelayMilliseconds() * (2 ** $exponent));
        $jitter = $base === 0 ? 0 : random_int(0, intdiv($base, 2));

        return min($policy->maximumDelayMilliseconds(), $base + $jitter);
    }
}
