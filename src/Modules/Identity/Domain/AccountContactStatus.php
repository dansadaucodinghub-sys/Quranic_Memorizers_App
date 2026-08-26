<?php

declare(strict_types=1);

namespace Qmdb\Modules\Identity\Domain;

enum AccountContactStatus: string
{
    case UNVERIFIED = 'UNVERIFIED';
    case VERIFIED = 'VERIFIED';
    case REVOKED = 'REVOKED';
}
