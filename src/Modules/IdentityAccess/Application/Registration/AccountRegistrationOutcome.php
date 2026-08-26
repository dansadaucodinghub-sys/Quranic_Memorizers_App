<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Registration;

enum AccountRegistrationOutcome: string
{
    case ACCEPTED = 'ACCEPTED';
    case THROTTLED = 'THROTTLED';
    case IDEMPOTENCY_CONFLICT = 'IDEMPOTENCY_CONFLICT';
}
