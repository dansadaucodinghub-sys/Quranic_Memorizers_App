<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

final readonly class RecoveryCodeHash
{
    public function __construct(private string $bytes)
    {
        if (strlen($bytes) !== 32) {
            throw new \InvalidArgumentException('Recovery code hash must contain 32 bytes.');
        }
    }

    public function toBinary(): string
    {
        return $this->bytes;
    }

    /** @return array{hash: string} */
    public function __debugInfo(): array
    {
        return ['hash' => '[REDACTED]'];
    }
}
