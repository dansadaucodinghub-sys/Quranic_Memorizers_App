<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Domain;

enum PersonDuplicateCaseStatus: string
{
    case REPORTED = 'REPORTED';
    case CONSENT_REQUIRED = 'CONSENT_REQUIRED';
    case READY_FOR_REVIEW = 'READY_FOR_REVIEW';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case DISMISSED = 'DISMISSED';
    case RESOLVED = 'RESOLVED';
    case BLOCKED = 'BLOCKED';

    public function isTerminal(): bool
    {
        return in_array($this, [self::DISMISSED, self::RESOLVED, self::BLOCKED], true);
    }
}
