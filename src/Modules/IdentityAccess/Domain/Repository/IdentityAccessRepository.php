<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\Identity\Domain\Value\LookupHash;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationChallenge;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationChallengeId;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationTokenHash;
use Qmdb\Modules\IdentityAccess\Domain\IdempotencyClaimStatus;
use Qmdb\Modules\IdentityAccess\Domain\IdempotencySubmissionId;
use Qmdb\Modules\IdentityAccess\Domain\PendingVerificationTarget;
use Qmdb\Modules\IdentityAccess\Domain\RegistrationSubmissionId;
use Qmdb\Modules\IdentityAccess\Domain\VerificationPersistenceResult;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprint;

interface IdentityAccessRepository
{
    public function claimIdempotency(
        IdempotencySubmissionId $id,
        string $operation,
        IdentityFingerprint $fingerprint,
        DateTimeImmutable $now,
    ): IdempotencyClaimStatus;

    public function completeIdempotency(IdempotencySubmissionId $id, DateTimeImmutable $now): void;

    public function historicalEmailExists(LookupHash $lookupHash): bool;

    public function pendingVerificationTarget(LookupHash $lookupHash): ?PendingVerificationTarget;

    public function revokePendingChallenges(int $emailInternalId, DateTimeImmutable $now): void;

    public function createChallenge(
        int $emailInternalId,
        EmailVerificationChallengeId $challengeId,
        EmailVerificationTokenHash $tokenHash,
        int $maximumAttempts,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): void;

    public function lockChallenge(EmailVerificationChallengeId $challengeId): ?EmailVerificationChallenge;

    public function expireChallenge(EmailVerificationChallenge $challenge, DateTimeImmutable $now): void;

    public function failChallenge(EmailVerificationChallenge $challenge, DateTimeImmutable $now): void;

    public function consumeChallenge(
        EmailVerificationChallenge $challenge,
        DateTimeImmutable $now,
    ): VerificationPersistenceResult;
}
