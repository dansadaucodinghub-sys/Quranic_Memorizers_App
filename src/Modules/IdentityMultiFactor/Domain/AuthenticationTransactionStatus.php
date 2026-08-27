<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

enum AuthenticationTransactionStatus: string
{
    case PENDING = 'PENDING';
    case COMPLETED = 'COMPLETED';
    case EXPIRED = 'EXPIRED';
    case REVOKED = 'REVOKED';
}
