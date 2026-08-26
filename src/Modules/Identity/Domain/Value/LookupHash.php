<?php

declare(strict_types=1);

namespace Qmdb\Modules\Identity\Domain\Value;

use InvalidArgumentException;

final readonly class LookupHash
{
    public function __construct(private string $binary)
    {
        if (strlen($binary) !== 32) {
            throw new InvalidArgumentException('Lookup hash must be exactly 32 bytes.');
        }
    }

    public static function keyed(string $purpose, string $normalizedValue, string $key): self
    {
        if (strlen($key) < 32) {
            throw new InvalidArgumentException('Identity HMAC key must contain at least 32 bytes.');
        }

        return new self(hash_hmac('sha256', $purpose . "\0" . $normalizedValue, $key, true));
    }

    public function toBinary(): string
    {
        return $this->binary;
    }

    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }
}
