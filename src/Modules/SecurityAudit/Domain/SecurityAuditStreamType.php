<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Domain;

enum SecurityAuditStreamType: string
{
    case PLATFORM = 'PLATFORM';
    case ACCOUNT = 'ACCOUNT';
    case WORKSPACE = 'WORKSPACE';
}
