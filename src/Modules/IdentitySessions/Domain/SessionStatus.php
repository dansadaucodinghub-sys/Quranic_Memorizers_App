<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Domain;

enum SessionStatus: string
{
    case ACTIVE = 'ACTIVE';
    case REVOKED = 'REVOKED';
    case EXPIRED = 'EXPIRED';
}
