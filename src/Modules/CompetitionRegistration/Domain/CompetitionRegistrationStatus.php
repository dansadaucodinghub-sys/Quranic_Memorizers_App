<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionRegistration\Domain;

enum CompetitionRegistrationStatus: string
{
    case SUBMITTED = 'SUBMITTED';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case APPROVED = 'APPROVED';
    case WAITLISTED = 'WAITLISTED';
    case REJECTED = 'REJECTED';
    case WITHDRAWN = 'WITHDRAWN';
    case ROSTERED = 'ROSTERED';
    case CANCELLED = 'CANCELLED';

    public function isTerminal(): bool
    {
        return in_array($this, [self::REJECTED, self::WITHDRAWN, self::ROSTERED, self::CANCELLED], true);
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::SUBMITTED => in_array($target, [self::UNDER_REVIEW, self::APPROVED, self::WAITLISTED, self::REJECTED, self::WITHDRAWN, self::CANCELLED], true),
            self::UNDER_REVIEW => in_array($target, [self::APPROVED, self::WAITLISTED, self::REJECTED, self::WITHDRAWN, self::CANCELLED], true),
            self::WAITLISTED => in_array($target, [self::APPROVED, self::WITHDRAWN, self::CANCELLED], true),
            self::APPROVED => in_array($target, [self::WITHDRAWN, self::ROSTERED, self::CANCELLED], true),
            self::REJECTED, self::WITHDRAWN, self::ROSTERED, self::CANCELLED => false,
        };
    }
}
