<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Metadata;

final readonly class SchemaMetadataReport
{
    public function __construct(
        public SchemaMetadataStatus $status,
        public string $safeCode,
    ) {
    }

    public function isReady(): bool
    {
        return $this->status === SchemaMetadataStatus::READY;
    }
}
