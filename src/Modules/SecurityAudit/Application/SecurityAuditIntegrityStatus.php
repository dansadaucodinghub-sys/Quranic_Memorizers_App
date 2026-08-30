<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

use DateTimeImmutable;

final readonly class SecurityAuditIntegrityStatus
{
    public function __construct(
        public int $streamCount,
        public int $eventCount,
        public ?int $latestCheckpointNumber,
        public ?DateTimeImmutable $latestCheckpointAt,
        public ?string $latestCheckpointHash,
        public string $externalPublicationStatus = 'Not configured',
    ) {
    }
}
