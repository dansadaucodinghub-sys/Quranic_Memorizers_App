<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

use Qmdb\Modules\IdentitySessions\Domain\AuthenticationOutcome;

final readonly class SessionAuthenticationResult
{
    private function __construct(
        public AuthenticationOutcome $outcome,
        public ?AuthenticatedAccountContext $context,
        public ?AuthenticationCookieInstruction $cookieInstruction,
    ) {
    }

    public static function anonymous(): self
    {
        return new self(AuthenticationOutcome::ANONYMOUS, null, null);
    }

    public static function invalid(
        AuthenticationOutcome $outcome,
        AuthenticationCookieInstruction $clear,
    ): self {
        return new self($outcome, null, $clear);
    }

    public static function authenticated(
        AuthenticatedAccountContext $context,
        ?AuthenticationCookieInstruction $rotation = null,
    ): self {
        return new self(AuthenticationOutcome::AUTHENTICATED, $context, $rotation);
    }
}
