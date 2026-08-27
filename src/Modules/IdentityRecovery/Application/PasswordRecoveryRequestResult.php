<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Application;

final readonly class PasswordRecoveryRequestResult
{
    private function __construct(
        public PasswordRecoveryRequestOutcome $outcome,
        public int $retryAfterSeconds = 0,
    ) {
    }

    public static function accepted(): self
    {
        return new self(PasswordRecoveryRequestOutcome::ACCEPTED);
    }

    public static function throttled(int $retryAfterSeconds): self
    {
        return new self(PasswordRecoveryRequestOutcome::THROTTLED, max(1, $retryAfterSeconds));
    }

    public static function conflict(): self
    {
        return new self(PasswordRecoveryRequestOutcome::IDEMPOTENCY_CONFLICT);
    }
}
