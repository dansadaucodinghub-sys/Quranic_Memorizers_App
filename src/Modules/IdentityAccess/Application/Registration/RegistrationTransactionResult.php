<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Registration;

use DateTimeImmutable;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationChallengeId;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationToken;

final readonly class RegistrationTransactionResult
{
    private function __construct(
        public bool $conflict,
        public ?EmailVerificationChallengeId $challengeId,
        public ?EmailVerificationToken $token,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }

    public static function acceptedWithoutDelivery(): self
    {
        return new self(false, null, null, null);
    }

    public static function acceptedWithDelivery(
        EmailVerificationChallengeId $challengeId,
        EmailVerificationToken $token,
        DateTimeImmutable $expiresAt,
    ): self {
        return new self(false, $challengeId, $token, $expiresAt);
    }

    public static function conflict(): self
    {
        return new self(true, null, null, null);
    }
}
