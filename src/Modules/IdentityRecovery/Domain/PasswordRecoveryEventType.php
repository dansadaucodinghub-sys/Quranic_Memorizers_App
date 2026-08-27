<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Domain;

enum PasswordRecoveryEventType: string
{
    case REQUESTED = 'REQUESTED';
    case DELIVERY_FAILED = 'DELIVERY_FAILED';
    case TOKEN_REJECTED = 'TOKEN_REJECTED';
    case EXPIRED = 'EXPIRED';
    case REVOKED = 'REVOKED';
    case COMPLETED = 'COMPLETED';
}
