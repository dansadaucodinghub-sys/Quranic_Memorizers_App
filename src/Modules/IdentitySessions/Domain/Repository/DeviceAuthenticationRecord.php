<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\DeviceStatus;
use Qmdb\Modules\IdentitySessions\Domain\DeviceTokenHash;

final readonly class DeviceAuthenticationRecord
{
    public function __construct(
        public int $internalId,
        public DeviceId $publicId,
        public int $accountInternalId,
        public DeviceTokenHash $tokenHash,
        public DeviceStatus $status,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastSeenAt,
    ) {
    }

    /** @return array{token_hash: string} */
    public function __debugInfo(): array
    {
        return ['token_hash' => '[REDACTED]'];
    }
}
