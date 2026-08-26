<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Domain;

enum EmailVerificationChallengeStatus: string
{
    case PENDING = 'PENDING';
    case CONSUMED = 'CONSUMED';
    case EXPIRED = 'EXPIRED';
    case REVOKED = 'REVOKED';
}
