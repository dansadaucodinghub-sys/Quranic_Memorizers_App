<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\MySql;

use Qmdb\Shared\Database\Transaction\Sleeper;

final class RecordingSleeper implements Sleeper
{
    /** @var list<int> */
    public array $delays = [];

    public function sleepMilliseconds(int $milliseconds): void
    {
        $this->delays[] = $milliseconds;
    }
}
