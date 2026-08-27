<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

enum RecoveryCodeStatus: string
{
    case ACTIVE = 'ACTIVE';
    case CONSUMED = 'CONSUMED';
}
