<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Registration;

final readonly class AccountRegistrationResult
{
    private function __construct(public AccountRegistrationOutcome $outcome, public int $retryAfterSeconds)
    {
    }

    public static function accepted(): self
    {
        return new self(AccountRegistrationOutcome::ACCEPTED, 0);
    }

    public static function throttled(int $retryAfterSeconds): self
    {
        return new self(AccountRegistrationOutcome::THROTTLED, max(1, $retryAfterSeconds));
    }

    public static function conflict(): self
    {
        return new self(AccountRegistrationOutcome::IDEMPOTENCY_CONFLICT, 0);
    }
}
