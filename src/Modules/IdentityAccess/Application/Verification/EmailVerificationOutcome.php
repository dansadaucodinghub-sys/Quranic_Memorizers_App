<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Verification;

enum EmailVerificationOutcome: string
{
    case COMPLETED = 'COMPLETED';
    case INVALID = 'INVALID';
    case THROTTLED = 'THROTTLED';
}
