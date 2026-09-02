<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Domain;

enum PersonDuplicateConsentDecision: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case DECLINED = 'DECLINED';
}
