<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Domain;

enum VerificationPersistenceResult: string
{
    case COMPLETED = 'COMPLETED';
    case ALREADY_COMPLETED = 'ALREADY_COMPLETED';
    case INVALID = 'INVALID';
}
