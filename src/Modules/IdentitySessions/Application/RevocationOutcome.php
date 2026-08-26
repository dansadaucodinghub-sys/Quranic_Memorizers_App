<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

enum RevocationOutcome: string
{
    case REVOKED = 'REVOKED';
    case ALREADY_INACTIVE = 'ALREADY_INACTIVE';
    case NOT_FOUND = 'NOT_FOUND';
    case CURRENT_RESOURCE = 'CURRENT_RESOURCE';
    case VERSION_CONFLICT = 'VERSION_CONFLICT';
}
