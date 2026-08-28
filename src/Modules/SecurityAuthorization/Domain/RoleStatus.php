<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

enum RoleStatus: string
{
    case ACTIVE = 'ACTIVE';
    case RETIRED = 'RETIRED';
}
