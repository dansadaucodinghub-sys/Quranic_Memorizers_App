<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Authentication;

final readonly class PasswordAuthenticationResult
{
    private function __construct(
        public PasswordAuthenticationOutcome $outcome,
        private ?VerifiedAccountPrincipal $principal,
        public bool $passwordRehashRequired,
        public int $retryAfterSeconds,
    ) {
    }

    public static function verified(VerifiedAccountPrincipal $principal, bool $rehashRequired): self
    {
        return new self(PasswordAuthenticationOutcome::VERIFIED, $principal, $rehashRequired, 0);
    }

    public static function invalid(): self
    {
        return new self(PasswordAuthenticationOutcome::INVALID_CREDENTIALS, null, false, 0);
    }

    public static function throttled(int $retryAfterSeconds): self
    {
        return new self(PasswordAuthenticationOutcome::THROTTLED, null, false, max(1, $retryAfterSeconds));
    }

    public function verifiedPrincipal(): VerifiedAccountPrincipal
    {
        if ($this->principal === null || $this->outcome !== PasswordAuthenticationOutcome::VERIFIED) {
            throw new \LogicException('Authentication result does not contain a verified principal.');
        }

        return $this->principal;
    }
}
