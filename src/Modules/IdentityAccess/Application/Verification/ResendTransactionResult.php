<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Verification;

use DateTimeImmutable;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationChallengeId;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationToken;

final readonly class ResendTransactionResult
{
    private function __construct(
        public bool $conflict,
        public ?EmailVerificationChallengeId $challengeId,
        public ?EmailVerificationToken $token,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }

    public static function accepted(): self
    {
        return new self(false, null, null, null);
    }

    public static function delivery(
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
