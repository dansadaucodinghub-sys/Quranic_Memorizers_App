<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

enum AccountMfaPreferredMethod: string
{
    case TOTP = 'TOTP';
    case PASSKEY = 'PASSKEY';
}
