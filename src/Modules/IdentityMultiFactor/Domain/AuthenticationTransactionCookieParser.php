<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

use Qmdb\Shared\Identifier\UuidV7;

final readonly class AuthenticationTransactionCookieParser
{
    public function parse(string $value): AuthenticationTransactionCookieValue
    {
        $parts = explode('.', $value);
        if (count($parts) !== 3 || $parts[0] !== 'v1') {
            throw new \InvalidArgumentException('Authentication transaction cookie is malformed.');
        }

        return new AuthenticationTransactionCookieValue(
            UuidV7::fromString($parts[1]),
            AuthenticationTransactionSecret::fromEncoded($parts[2]),
        );
    }
}
