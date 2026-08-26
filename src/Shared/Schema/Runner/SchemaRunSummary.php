<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Runner;

final readonly class SchemaRunSummary
{
    /** @param list<string> $processedIds */
    public function __construct(
        public array $processedIds,
        public bool $noOp,
    ) {
    }
}
