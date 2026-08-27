<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Domain;

enum PasswordRecoveryChallengeStatus: string
{
    case PENDING = 'PENDING';
    case CONSUMED = 'CONSUMED';
    case EXPIRED = 'EXPIRED';
    case REVOKED = 'REVOKED';
}
