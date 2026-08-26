<?php

declare(strict_types=1);

namespace Qmdb\Modules\Tenancy\Domain;

enum MembershipStatus: string
{
    case INVITED = 'INVITED';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case REVOKED = 'REVOKED';
}
