<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Verification;

final readonly class EmailVerificationResult
{
    private function __construct(public EmailVerificationOutcome $outcome, public int $retryAfterSeconds)
    {
    }

    public static function completed(): self
    {
        return new self(EmailVerificationOutcome::COMPLETED, 0);
    }

    public static function invalid(): self
    {
        return new self(EmailVerificationOutcome::INVALID, 0);
    }

    public static function throttled(int $retryAfterSeconds): self
    {
        return new self(EmailVerificationOutcome::THROTTLED, max(1, $retryAfterSeconds));
    }
}
