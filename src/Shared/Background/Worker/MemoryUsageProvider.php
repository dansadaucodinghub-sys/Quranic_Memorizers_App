<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Worker;

interface MemoryUsageProvider
{
    public function bytes(): int;
}
