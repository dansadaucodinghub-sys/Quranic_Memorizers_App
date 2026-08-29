<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Domain;

enum PrivilegedAccessType: string
{
    case TEMPORARY_PRIVILEGE = 'TEMPORARY_PRIVILEGE';
    case SUPPORT_ACCESS = 'SUPPORT_ACCESS';
    case BREAK_GLASS = 'BREAK_GLASS';

    public function requiresReview(): bool
    {
        return $this !== self::TEMPORARY_PRIVILEGE;
    }
}
