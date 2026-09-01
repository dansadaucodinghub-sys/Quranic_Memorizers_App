<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

enum GuardianshipStatus: string
{
    case ACTIVE = 'ACTIVE';
    case REVOKED = 'REVOKED';
}
