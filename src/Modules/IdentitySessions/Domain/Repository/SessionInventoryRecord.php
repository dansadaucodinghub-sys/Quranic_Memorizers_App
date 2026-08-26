<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Domain\Repository;

final readonly class SessionInventoryRecord
{
    public function __construct(
        public string $publicId,
        public string $devicePublicId,
        public string $status,
        public int $version,
        public string $issuedAt,
        public string $lastSeenAt,
        public string $idleExpiresAt,
        public string $absoluteExpiresAt,
    ) {
    }
}
