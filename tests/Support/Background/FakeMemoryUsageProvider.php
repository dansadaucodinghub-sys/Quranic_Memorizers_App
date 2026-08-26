<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Background;

use Qmdb\Shared\Background\Worker\MemoryUsageProvider;

final class FakeMemoryUsageProvider implements MemoryUsageProvider
{
    /** @param list<int> $values */
    public function __construct(private array $values)
    {
    }

    public function bytes(): int
    {
        return array_shift($this->values) ?? end($this->values) ?: 0;
    }
}
