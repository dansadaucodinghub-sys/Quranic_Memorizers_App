<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

final readonly class AuthenticationTransactionSecretHash
{
    public function __construct(private string $bytes)
    {
        if (strlen($bytes) !== 32) {
            throw new \InvalidArgumentException('Authentication transaction secret hash must contain 32 bytes.');
        }
    }

    public function toBinary(): string
    {
        return $this->bytes;
    }

    public function matches(AuthenticationTransactionSecret $secret): bool
    {
        return hash_equals($this->bytes, $secret->hash()->toBinary());
    }

    /** @return array{hash: string} */
    public function __debugInfo(): array
    {
        return ['hash' => '[REDACTED]'];
    }
}
