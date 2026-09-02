<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Domain;

enum ProfileClaimPairingStatus: string
{
    case ACTIVE = 'ACTIVE';
    case CONSUMED = 'CONSUMED';
    case REVOKED = 'REVOKED';
    case EXPIRED = 'EXPIRED';
    case ATTEMPTS_EXHAUSTED = 'ATTEMPTS_EXHAUSTED';

    public function permits(self $next): bool
    {
        return $this === self::ACTIVE && in_array($next, [self::CONSUMED, self::REVOKED, self::EXPIRED, self::ATTEMPTS_EXHAUSTED], true);
    }
}
