<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Seed;

final readonly class SeedRecord
{
    public function __construct(
        public string $id,
        public string $checksum,
        public SeedStatus $status,
        public int $batch,
    ) {
    }
}
