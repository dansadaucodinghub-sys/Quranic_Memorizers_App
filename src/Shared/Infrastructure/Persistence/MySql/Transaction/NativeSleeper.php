<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction;

use InvalidArgumentException;
use Qmdb\Shared\Database\Transaction\Sleeper;

final readonly class NativeSleeper implements Sleeper
{
    public function sleepMilliseconds(int $milliseconds): void
    {
        if ($milliseconds < 0 || $milliseconds > 60_000) {
            throw new InvalidArgumentException('Sleep duration is invalid.');
        }
        if ($milliseconds > 0) {
            usleep($milliseconds * 1_000);
        }
    }
}
