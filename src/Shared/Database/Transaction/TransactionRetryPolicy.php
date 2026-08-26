<?php

declare(strict_types=1);

namespace Qmdb\Shared\Database\Transaction;

use InvalidArgumentException;

final readonly class TransactionRetryPolicy
{
    public function __construct(
        private int $maximumAttempts,
        private int $baseDelayMilliseconds,
        private int $maximumDelayMilliseconds,
    ) {
        if ($maximumAttempts < 1 || $maximumAttempts > 10) {
            throw new InvalidArgumentException('Transaction retry attempts must be between 1 and 10.');
        }
        if ($baseDelayMilliseconds < 0 || $baseDelayMilliseconds > 10_000) {
            throw new InvalidArgumentException('Transaction retry base delay is invalid.');
        }
        if ($maximumDelayMilliseconds < $baseDelayMilliseconds || $maximumDelayMilliseconds > 60_000) {
            throw new InvalidArgumentException('Transaction retry maximum delay is invalid.');
        }
    }

    public function maximumAttempts(): int
    {
        return $this->maximumAttempts;
    }

    public function baseDelayMilliseconds(): int
    {
        return $this->baseDelayMilliseconds;
    }

    public function maximumDelayMilliseconds(): int
    {
        return $this->maximumDelayMilliseconds;
    }
}
