<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Domain;

use InvalidArgumentException;

final readonly class SessionTokenHash
{
    public function __construct(private string $binary)
    {
        if (strlen($binary) !== 32) {
            throw new InvalidArgumentException('Session token hash is invalid.');
        }
    }

    public function matches(SessionTokenSecret $secret): bool
    {
        return hash_equals($this->binary, $secret->hash()->toBinary());
    }

    public function toBinary(): string
    {
        return $this->binary;
    }

    /** @return array{value: string} */
    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }
}
