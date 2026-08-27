<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\EncryptedTotpSecret;
use Qmdb\Modules\IdentityMultiFactor\Domain\TotpAuthenticator;

interface TotpAuthenticatorRepository
{
    public function createPendingTotp(
        int $accountInternalId,
        string $publicId,
        EncryptedTotpSecret $secret,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): TotpAuthenticator;

    public function findTotp(
        int $accountInternalId,
        string $publicId,
        bool $forUpdate = false,
    ): ?TotpAuthenticator;

    public function findActiveTotp(int $accountInternalId, bool $forUpdate = false): ?TotpAuthenticator;

    public function confirmTotp(TotpAuthenticator $authenticator, int $counter, DateTimeImmutable $now): bool;

    public function acceptTotpCounter(TotpAuthenticator $authenticator, int $counter, DateTimeImmutable $now): bool;

    public function revokeTotp(TotpAuthenticator $authenticator, DateTimeImmutable $now): bool;
}
