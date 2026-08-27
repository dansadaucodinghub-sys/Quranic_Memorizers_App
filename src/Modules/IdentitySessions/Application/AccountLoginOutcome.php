<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

enum AccountLoginOutcome: string
{
    case AUTHENTICATED = 'AUTHENTICATED';
    case INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    case THROTTLED = 'THROTTLED';
    case REPLAYED = 'REPLAYED';
    case MFA_REQUIRED = 'MFA_REQUIRED';
}
