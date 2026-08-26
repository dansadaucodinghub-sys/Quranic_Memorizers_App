<?php

declare(strict_types=1);

namespace Qmdb\Modules\Identity\Domain;

enum AccountStatus: string
{
    case PENDING_VERIFICATION = 'PENDING_VERIFICATION';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case CLOSED = 'CLOSED';
}
