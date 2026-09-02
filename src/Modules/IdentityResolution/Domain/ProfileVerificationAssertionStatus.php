<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Domain;

enum ProfileVerificationAssertionStatus: string
{
    case ACTIVE = 'ACTIVE';
    case REVOKED = 'REVOKED';
}
