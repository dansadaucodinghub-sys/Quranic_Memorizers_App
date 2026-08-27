<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticationCookieInstruction;

final readonly class MfaLoginResult
{
    /** @param list<AuthenticationCookieInstruction> $cookies */
    private function __construct(
        public bool $succeeded,
        public array $cookies,
        public string $reason,
    ) {
    }

    /** @param list<AuthenticationCookieInstruction> $cookies */
    public static function authenticated(array $cookies): self
    {
        return new self(true, $cookies, 'AUTHENTICATED');
    }

    public static function rejected(string $reason): self
    {
        return new self(false, [], $reason);
    }
}
