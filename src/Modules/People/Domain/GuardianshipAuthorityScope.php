<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

enum GuardianshipAuthorityScope: string
{
    case PROFILE_MANAGEMENT = 'PROFILE_MANAGEMENT';
}
