<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Application;

enum PasswordRecoveryRequestOutcome: string
{
    case ACCEPTED = 'ACCEPTED';
    case THROTTLED = 'THROTTLED';
    case IDEMPOTENCY_CONFLICT = 'IDEMPOTENCY_CONFLICT';
}
