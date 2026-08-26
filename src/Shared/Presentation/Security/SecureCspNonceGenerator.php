<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\Security;

final readonly class SecureCspNonceGenerator implements CspNonceGenerator
{
    public function generate(): CspNonce
    {
        return new CspNonce(rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '='));
    }
}
