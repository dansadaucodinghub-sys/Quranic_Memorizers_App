<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Verification;

final readonly class EmailVerificationResendResult
{
    private function __construct(public EmailVerificationResendOutcome $outcome, public int $retryAfterSeconds)
    {
    }

    public static function accepted(): self
    {
        return new self(EmailVerificationResendOutcome::ACCEPTED, 0);
    }

    public static function throttled(int $retryAfterSeconds): self
    {
        return new self(EmailVerificationResendOutcome::THROTTLED, max(1, $retryAfterSeconds));
    }

    public static function conflict(): self
    {
        return new self(EmailVerificationResendOutcome::IDEMPOTENCY_CONFLICT, 0);
    }
}
