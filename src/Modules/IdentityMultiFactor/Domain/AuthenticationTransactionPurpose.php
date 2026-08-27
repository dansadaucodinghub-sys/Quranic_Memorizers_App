<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

enum AuthenticationTransactionPurpose: string
{
    case LOGIN_MFA = 'LOGIN_MFA';
    case PASSKEY_LOGIN = 'PASSKEY_LOGIN';
    case STEP_UP = 'STEP_UP';
}
