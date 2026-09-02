<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Domain;

enum ProfileVerificationAssertionType: string
{
    case ACCOUNT_CLAIMED = 'ACCOUNT_CLAIMED';
    case GUARDIAN_CONFIRMED = 'GUARDIAN_CONFIRMED';
    case QMDB_RECORD_REVIEWED = 'QMDB_RECORD_REVIEWED';
}
