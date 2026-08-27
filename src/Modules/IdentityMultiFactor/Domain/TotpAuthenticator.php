<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

use DateTimeImmutable;

final readonly class TotpAuthenticator
{
    public function __construct(
        public int $internalId,
        public string $publicId,
        public int $accountInternalId,
        public EncryptedTotpSecret $secret,
        public TotpAuthenticatorStatus $status,
        public ?int $lastAcceptedCounter,
        public ?DateTimeImmutable $enrollmentExpiresAt,
        public ?DateTimeImmutable $confirmedAt,
        public ?DateTimeImmutable $revokedAt,
        public int $version,
        public DateTimeImmutable $createdAt,
    ) {
        if (
            $version < 1
            || $status === TotpAuthenticatorStatus::PENDING && $enrollmentExpiresAt === null
            || $status === TotpAuthenticatorStatus::ACTIVE && $confirmedAt === null
            || $status === TotpAuthenticatorStatus::REVOKED && $revokedAt === null
        ) {
            throw new \InvalidArgumentException('TOTP authenticator is inconsistent.');
        }
    }
}
