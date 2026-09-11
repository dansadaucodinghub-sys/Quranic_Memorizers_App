<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionConfiguration\Domain;

enum CompetitionEditionStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case REGISTRATION_OPEN = 'REGISTRATION_OPEN';
    case REGISTRATION_CLOSED = 'REGISTRATION_CLOSED';
    case ROSTER_FINALIZED = 'ROSTER_FINALIZED';
    case CANCELLED = 'CANCELLED';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::DRAFT => in_array($target, [self::PUBLISHED, self::CANCELLED], true),
            self::PUBLISHED => in_array($target, [self::REGISTRATION_OPEN, self::CANCELLED], true),
            self::REGISTRATION_OPEN => in_array($target, [self::REGISTRATION_CLOSED, self::CANCELLED], true),
            self::REGISTRATION_CLOSED => in_array($target, [self::REGISTRATION_OPEN, self::ROSTER_FINALIZED, self::CANCELLED], true),
            self::ROSTER_FINALIZED, self::CANCELLED => false,
        };
    }
}
