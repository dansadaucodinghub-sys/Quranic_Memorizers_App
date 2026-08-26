<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Domain;

enum IdempotencyClaimStatus: string
{
    case CLAIMED = 'CLAIMED';
    case REPLAY = 'REPLAY';
    case CONFLICT = 'CONFLICT';
}
