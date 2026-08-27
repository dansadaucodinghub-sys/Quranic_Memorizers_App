<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

enum RecoveryCodeSetStatus: string
{
    case ACTIVE = 'ACTIVE';
    case REVOKED = 'REVOKED';
    case EXHAUSTED = 'EXHAUSTED';
}
