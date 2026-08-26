<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Domain;

enum AuthenticationOutcome: string
{
    case ANONYMOUS = 'ANONYMOUS';
    case AUTHENTICATED = 'AUTHENTICATED';
    case INVALID_COOKIE = 'INVALID_COOKIE';
    case EXPIRED = 'EXPIRED';
    case REVOKED = 'REVOKED';
    case ACCOUNT_NOT_ACTIVE = 'ACCOUNT_NOT_ACTIVE';
    case DEVICE_NOT_ACTIVE = 'DEVICE_NOT_ACTIVE';
}
