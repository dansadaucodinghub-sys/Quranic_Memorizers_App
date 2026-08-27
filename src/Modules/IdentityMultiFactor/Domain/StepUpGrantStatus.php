<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

enum StepUpGrantStatus: string
{
    case ACTIVE = 'ACTIVE';
    case CONSUMED = 'CONSUMED';
    case EXPIRED = 'EXPIRED';
    case REVOKED = 'REVOKED';
}
