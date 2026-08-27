<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

enum AuthenticationMethod: string
{
    case PASSWORD = 'PASSWORD';
    case TOTP = 'TOTP';
    case RECOVERY_CODE = 'RECOVERY_CODE';
    case PASSKEY = 'PASSKEY';
}
