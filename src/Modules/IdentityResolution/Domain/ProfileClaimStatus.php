<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Domain;

enum ProfileClaimStatus: string
{
    case PENDING_ACCEPTANCE = 'PENDING_ACCEPTANCE';
    case ACCEPTED = 'ACCEPTED';
    case DECLINED = 'DECLINED';
    case REVOKED = 'REVOKED';
    case EXPIRED = 'EXPIRED';

    public function permits(self $next): bool
    {
        return $this === self::PENDING_ACCEPTANCE && in_array($next, [self::ACCEPTED, self::DECLINED, self::REVOKED, self::EXPIRED], true);
    }
}
