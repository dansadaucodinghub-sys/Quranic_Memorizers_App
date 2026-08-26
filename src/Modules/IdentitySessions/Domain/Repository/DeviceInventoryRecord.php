<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Domain\Repository;

final readonly class DeviceInventoryRecord
{
    public function __construct(
        public string $publicId,
        public string $status,
        public int $version,
        public string $createdAt,
        public string $lastSeenAt,
        public int $activeSessionCount,
    ) {
    }
}
