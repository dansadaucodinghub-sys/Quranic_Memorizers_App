<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Domain;

enum PrivilegedAccessActivationStatus: string
{
    case ACTIVE = 'ACTIVE';
    case ENDED = 'ENDED';
    case EXPIRED = 'EXPIRED';
    case REVOKED = 'REVOKED';

    public function isEffective(\DateTimeImmutable $now, \DateTimeImmutable $expiresAt): bool
    {
        return $this === self::ACTIVE && $now < $expiresAt;
    }
}
