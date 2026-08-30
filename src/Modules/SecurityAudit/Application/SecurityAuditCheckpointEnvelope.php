<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

use DateTimeImmutable;

/**
 * Contains only integrity evidence: no metadata, personal data, permissions, or key material.
 */
final readonly class SecurityAuditCheckpointEnvelope
{
    public function __construct(
        public string $publicId,
        public int $number,
        public string $checkpointHashHex,
        public string $previousCheckpointHashHex,
        public string $headsDigestHex,
        public int $streamCount,
        public int $eventCount,
        public int $integrityKeyVersion,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
