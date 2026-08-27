<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\WebAuthnCeremony;
use Qmdb\Modules\IdentityMultiFactor\Domain\WebAuthnCeremonyPurpose;
use Qmdb\Modules\IdentityMultiFactor\Domain\WebAuthnChallenge;

interface WebAuthnCeremonyRepository
{
    public function revokePendingCeremonies(
        ?int $sessionInternalId,
        ?int $authenticationTransactionInternalId,
        WebAuthnCeremonyPurpose $purpose,
        DateTimeImmutable $now,
    ): void;

    public function createCeremony(
        ?int $accountInternalId,
        ?int $sessionInternalId,
        ?int $authenticationTransactionInternalId,
        WebAuthnCeremonyPurpose $purpose,
        WebAuthnChallenge $challenge,
        int $maximumAttempts,
        DateTimeImmutable $now,
        DateTimeImmutable $expiresAt,
    ): WebAuthnCeremony;

    public function findCeremony(string $publicId, bool $forUpdate = false): ?WebAuthnCeremony;

    public function recordCeremonyFailure(WebAuthnCeremony $ceremony, DateTimeImmutable $now): bool;

    public function consumeCeremony(WebAuthnCeremony $ceremony, DateTimeImmutable $now): bool;
}
