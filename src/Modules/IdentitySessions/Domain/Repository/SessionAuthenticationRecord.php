<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\DeviceStatus;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\IdentitySessions\Domain\SessionStatus;
use Qmdb\Modules\IdentitySessions\Domain\SessionTokenHash;
use Qmdb\Modules\IdentityMultiFactor\Domain\SessionAuthenticationAssurance;

final readonly class SessionAuthenticationRecord
{
    public function __construct(
        public int $internalId,
        public SessionId $publicId,
        public int $accountInternalId,
        public AccountId $accountId,
        public AccountStatus $accountStatus,
        public int $deviceInternalId,
        public DeviceId $deviceId,
        public DeviceStatus $deviceStatus,
        public SessionTokenHash $currentTokenHash,
        public ?SessionTokenHash $previousTokenHash,
        public ?DateTimeImmutable $previousTokenExpiresAt,
        public SessionStatus $status,
        public int $version,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $authenticatedAt,
        public DateTimeImmutable $lastSeenAt,
        public DateTimeImmutable $idleExpiresAt,
        public DateTimeImmutable $absoluteExpiresAt,
        public DateTimeImmutable $rotatedAt,
        public SessionAuthenticationAssurance $assurance,
    ) {
    }

    /** @return array{token_hashes: string} */
    public function __debugInfo(): array
    {
        return ['token_hashes' => '[REDACTED]'];
    }
}
