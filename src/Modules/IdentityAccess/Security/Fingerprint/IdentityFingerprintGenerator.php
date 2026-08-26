<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Fingerprint;

interface IdentityFingerprintGenerator
{
    public function generate(string $domain, string $value): IdentityFingerprint;
}
