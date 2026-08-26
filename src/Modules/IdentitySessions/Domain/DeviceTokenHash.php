<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Domain;

use InvalidArgumentException;

final readonly class DeviceTokenHash
{
    public function __construct(private string $binary)
    {
        if (strlen($binary) !== 32) {
            throw new InvalidArgumentException('Device token hash is invalid.');
        }
    }

    public function matches(DeviceTokenSecret $secret): bool
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
