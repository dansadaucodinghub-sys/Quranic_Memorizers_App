<?php

declare(strict_types=1);

namespace Qmdb\Shared\Identifier;

final readonly class SecureRandomRuntimeIdentifierGenerator implements RuntimeIdentifierGenerator
{
    private const ENTROPY_BYTES = 16;

    public function generate(): RuntimeIdentifier
    {
        return RuntimeIdentifier::fromString(bin2hex(random_bytes(self::ENTROPY_BYTES)));
    }
}
