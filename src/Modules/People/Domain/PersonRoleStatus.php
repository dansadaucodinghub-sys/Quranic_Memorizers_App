<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

enum PersonRoleStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
}
