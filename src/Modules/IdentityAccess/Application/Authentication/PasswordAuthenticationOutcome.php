<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Authentication;

enum PasswordAuthenticationOutcome: string
{
    case VERIFIED = 'VERIFIED';
    case INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    case THROTTLED = 'THROTTLED';
}
