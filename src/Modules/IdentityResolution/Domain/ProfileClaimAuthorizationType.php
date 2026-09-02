<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Domain;

enum ProfileClaimAuthorizationType: string
{
    case GUARDIAN = 'GUARDIAN';
    case PLATFORM_RECORD_REVIEW = 'PLATFORM_RECORD_REVIEW';
}
