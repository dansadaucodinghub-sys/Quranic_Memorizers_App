<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Domain;

enum SecurityEventActorKind: string
{
    case ACCOUNT = 'ACCOUNT';
    case SYSTEM = 'SYSTEM';
}
