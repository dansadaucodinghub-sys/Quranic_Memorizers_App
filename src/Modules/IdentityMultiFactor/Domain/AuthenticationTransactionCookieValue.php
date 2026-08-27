<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

use Qmdb\Shared\Identifier\UuidV7;

final readonly class AuthenticationTransactionCookieValue
{
    public function __construct(
        public UuidV7 $transactionId,
        private AuthenticationTransactionSecret $secret,
    ) {
    }

    public function revealForCookie(): string
    {
        return 'v1.' . $this->transactionId->toString() . '.' . $this->secret->revealForCookie();
    }

    public function secretForVerification(): AuthenticationTransactionSecret
    {
        return $this->secret;
    }

    /** @return array{cookie: string} */
    public function __debugInfo(): array
    {
        return ['cookie' => '[REDACTED]'];
    }
}
