<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Domain;

enum DeviceStatus: string
{
    case ACTIVE = 'ACTIVE';
    case REVOKED = 'REVOKED';
}
