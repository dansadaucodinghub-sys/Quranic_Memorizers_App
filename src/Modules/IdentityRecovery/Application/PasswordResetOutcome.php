<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Application;

enum PasswordResetOutcome: string
{
    case COMPLETED = 'COMPLETED';
    case INVALID = 'INVALID';
    case THROTTLED = 'THROTTLED';
    case IDEMPOTENCY_CONFLICT = 'IDEMPOTENCY_CONFLICT';
}
