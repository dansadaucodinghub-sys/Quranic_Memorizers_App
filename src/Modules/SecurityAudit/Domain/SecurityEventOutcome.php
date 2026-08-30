<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Domain;

enum SecurityEventOutcome: string
{
    case SUCCESS = 'SUCCESS';
    case DENIED = 'DENIED';
    case FAILURE = 'FAILURE';
}
