<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Background;

use Qmdb\Shared\Database\Transaction\Sleeper;

final class RecordingSleeper implements Sleeper
{
    /** @var list<int> */
    public array $delays = [];

    public function __construct(private ?FakeSignalController $signals = null)
    {
    }

    public function sleepMilliseconds(int $milliseconds): void
    {
        $this->delays[] = $milliseconds;
        $this->signals?->requestStop();
    }
}
