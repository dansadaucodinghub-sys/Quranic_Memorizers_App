<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Domain;

enum PersonDuplicateConsentAuthorityType: string
{
    case SELF = 'SELF';
    case GUARDIAN = 'GUARDIAN';
}
