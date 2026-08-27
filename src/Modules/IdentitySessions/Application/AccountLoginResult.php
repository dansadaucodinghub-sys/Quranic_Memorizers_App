<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

final readonly class AccountLoginResult
{
    /** @param list<AuthenticationCookieInstruction> $cookieInstructions */
    private function __construct(
        public AccountLoginOutcome $outcome,
        public array $cookieInstructions,
        public int $retryAfterSeconds,
    ) {
    }

    /** @param list<AuthenticationCookieInstruction> $cookies */
    public static function authenticated(array $cookies): self
    {
        return new self(AccountLoginOutcome::AUTHENTICATED, $cookies, 0);
    }

    public static function invalid(): self
    {
        return new self(AccountLoginOutcome::INVALID_CREDENTIALS, [], 0);
    }

    public static function throttled(int $retryAfter): self
    {
        return new self(AccountLoginOutcome::THROTTLED, [], max(1, $retryAfter));
    }

    public static function replayed(): self
    {
        return new self(AccountLoginOutcome::REPLAYED, [], 0);
    }

    /** @param list<AuthenticationCookieInstruction> $cookies */
    public static function mfaRequired(array $cookies): self
    {
        return new self(AccountLoginOutcome::MFA_REQUIRED, $cookies, 0);
    }
}
