<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Domain;

use DateTimeImmutable;
use Qmdb\Modules\Identity\Domain\AccountContactStatus;
use Qmdb\Modules\Identity\Domain\AccountStatus;

final readonly class EmailVerificationChallenge
{
    public function __construct(
        public int $internalId,
        public EmailVerificationChallengeId $publicId,
        public int $emailInternalId,
        public int $accountInternalId,
        public int $accountVersion,
        public AccountContactStatus $emailStatus,
        public AccountStatus $accountStatus,
        public EmailVerificationTokenHash $tokenHash,
        public EmailVerificationChallengeStatus $status,
        public int $attemptCount,
        public int $maximumAttempts,
        public DateTimeImmutable $expiresAt,
        public int $version,
    ) {
    }
}
