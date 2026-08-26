<?php

declare(strict_types=1);

namespace Qmdb\Shared\Database\Transaction;

interface Sleeper
{
    public function sleepMilliseconds(int $milliseconds): void;
}
