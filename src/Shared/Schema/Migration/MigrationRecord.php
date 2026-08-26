<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Migration;

final readonly class MigrationRecord
{
    /** @param array<string, string> $appliedStepChecksums */
    public function __construct(
        public string $id,
        public string $checksum,
        public MigrationStatus $status,
        public int $batch,
        public array $appliedStepChecksums = [],
    ) {
    }
}
