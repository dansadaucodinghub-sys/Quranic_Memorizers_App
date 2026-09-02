<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Domain;

enum OrganizationAffiliationAssignmentStatus: string
{
    case PROPOSED = 'PROPOSED';
    case ACTIVE = 'ACTIVE';
    case REMOVED = 'REMOVED';
    case CANCELLED = 'CANCELLED';

    public function permits(self $next): bool
    {
        return match ($this) {
            self::PROPOSED => in_array($next, [self::ACTIVE, self::CANCELLED], true),
            self::ACTIVE => $next === self::REMOVED,
            default => false,
        };
    }
}
