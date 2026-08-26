<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Fingerprint;

use InvalidArgumentException;

final readonly class IdentityFingerprint
{
    public function __construct(private string $binary)
    {
        if (strlen($binary) !== 32) {
            throw new InvalidArgumentException('Identity fingerprint is invalid.');
        }
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
