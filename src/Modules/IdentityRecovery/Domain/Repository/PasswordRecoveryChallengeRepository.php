<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\Identity\Domain\Value\LookupHash;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordHashResult;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallenge;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallengeId;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryTokenHash;

interface PasswordRecoveryChallengeRepository
{
    public function targetByEmailHash(LookupHash $lookupHash): ?PasswordRecoveryTarget;

    public function createPending(
        PasswordRecoveryTarget $target,
        PasswordRecoveryChallengeId $id,
        PasswordRecoveryTokenHash $tokenHash,
        string $locale,
        int $maximumAttempts,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): int;

    public function findByPublicId(PasswordRecoveryChallengeId $id): ?PasswordRecoveryChallenge;

    public function lockByPublicId(PasswordRecoveryChallengeId $id): ?PasswordRecoveryChallenge;

    public function revokePendingForAccount(int $accountInternalId, DateTimeImmutable $now): int;

    public function incrementInvalidAttempt(PasswordRecoveryChallenge $challenge, DateTimeImmutable $now): void;

    public function markExpired(PasswordRecoveryChallenge $challenge, DateTimeImmutable $now): void;

    public function markConsumed(PasswordRecoveryChallenge $challenge, DateTimeImmutable $now): bool;

    public function replacePasswordCredential(
        int $accountInternalId,
        PasswordHashResult $hash,
        DateTimeImmutable $now,
    ): void;
}
