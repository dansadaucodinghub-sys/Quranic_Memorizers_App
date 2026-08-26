<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Worker;

final readonly class NativeMemoryUsageProvider implements MemoryUsageProvider
{
    public function bytes(): int
    {
        return memory_get_usage(true);
    }
}
