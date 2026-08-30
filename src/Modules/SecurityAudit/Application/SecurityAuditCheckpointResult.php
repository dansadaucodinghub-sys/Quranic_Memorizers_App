<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

final readonly class SecurityAuditCheckpointResult
{
    public function __construct(
        public bool $created,
        public ?string $publicId,
        public ?int $number,
        public int $streamCount,
        public int $eventCount,
    ) {
    }
}
