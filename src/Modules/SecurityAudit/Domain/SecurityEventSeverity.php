<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Domain;

enum SecurityEventSeverity: string
{
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case CRITICAL = 'CRITICAL';
}
