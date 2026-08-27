<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Application;

final readonly class PasswordResetResult
{
    private function __construct(public PasswordResetOutcome $outcome, public int $retryAfterSeconds = 0)
    {
    }

    public static function completed(): self
    {
        return new self(PasswordResetOutcome::COMPLETED);
    }

    public static function invalid(): self
    {
        return new self(PasswordResetOutcome::INVALID);
    }

    public static function throttled(int $retryAfterSeconds): self
    {
        return new self(PasswordResetOutcome::THROTTLED, max(1, $retryAfterSeconds));
    }

    public static function conflict(): self
    {
        return new self(PasswordResetOutcome::IDEMPOTENCY_CONFLICT);
    }
}
