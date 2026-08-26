<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Fingerprint;

use Qmdb\Modules\Identity\Domain\Value\LookupHash;
use SensitiveParameter;

final readonly class EmailLookupHashGenerator
{
    public function __construct(#[SensitiveParameter] private string $key)
    {
        if (strlen($key) < 32) {
            throw new \InvalidArgumentException('Email lookup key is invalid.');
        }
    }

    public function generate(string $normalizedEmail): LookupHash
    {
        return LookupHash::keyed('email', $normalizedEmail, $this->key);
    }
}
