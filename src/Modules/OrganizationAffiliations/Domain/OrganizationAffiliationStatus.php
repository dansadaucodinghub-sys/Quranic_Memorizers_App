<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Domain;

enum OrganizationAffiliationStatus: string
{
    case PENDING_ACCEPTANCE = 'PENDING_ACCEPTANCE';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case ENDED = 'ENDED';
    case DECLINED = 'DECLINED';
    case WITHDRAWN = 'WITHDRAWN';
    case EXPIRED = 'EXPIRED';

    public function permits(self $next): bool
    {
        return match ($this) {
            self::PENDING_ACCEPTANCE => in_array($next, [self::ACTIVE, self::DECLINED, self::WITHDRAWN, self::EXPIRED], true),
            self::ACTIVE => in_array($next, [self::SUSPENDED, self::ENDED], true),
            self::SUSPENDED => in_array($next, [self::ACTIVE, self::ENDED], true),
            default => false,
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::PENDING_ACCEPTANCE, self::ACTIVE, self::SUSPENDED], true);
    }
}
