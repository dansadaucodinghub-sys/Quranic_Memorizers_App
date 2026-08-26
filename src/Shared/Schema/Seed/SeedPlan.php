<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Seed;

final readonly class SeedPlan
{
    /** @param list<Seed> $pending */
    public function __construct(
        public array $pending,
        public bool $blocked,
    ) {
    }
}
