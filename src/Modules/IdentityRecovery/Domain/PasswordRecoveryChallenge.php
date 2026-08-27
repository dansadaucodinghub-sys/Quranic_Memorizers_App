<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Domain;

use DateTimeImmutable;
use DomainException;
use Qmdb\Modules\Identity\Domain\AccountContactStatus;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\Identity\Domain\Value\AccountId;

final readonly class PasswordRecoveryChallenge
{
    public function __construct(
        public int $internalId,
        public PasswordRecoveryChallengeId $id,
        public int $accountInternalId,
        public AccountId $accountId,
        public int $emailInternalId,
        public AccountStatus $accountStatus,
        public AccountContactStatus $emailStatus,
        public PasswordRecoveryTokenHash $tokenHash,
        public string $locale,
        public PasswordRecoveryChallengeStatus $status,
        public int $attemptCount,
        public int $maximumAttempts,
        public DateTimeImmutable $expiresAt,
        public int $version,
    ) {
        if (!in_array($locale, ['en', 'ar'], true)) {
            throw new DomainException('Password recovery locale is invalid.');
        }
        if ($attemptCount < 0 || $maximumAttempts < 1 || $attemptCount > $maximumAttempts || $version < 1) {
            throw new DomainException('Password recovery challenge counters are invalid.');
        }
    }

    public function accepts(PasswordRecoveryToken $token, DateTimeImmutable $now): bool
    {
        return $this->status === PasswordRecoveryChallengeStatus::PENDING
            && $this->attemptCount < $this->maximumAttempts
            && $now < $this->expiresAt
            && $this->tokenHash->matches($token);
    }
}
