<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

enum AccountMfaPolicyStatus: string
{
    case DISABLED = 'DISABLED';
    case ENABLED = 'ENABLED';
}
