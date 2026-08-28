<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

enum PermissionStatus: string
{
    case ACTIVE = 'ACTIVE';
    case RETIRED = 'RETIRED';
}
