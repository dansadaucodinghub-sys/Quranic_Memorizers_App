<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

enum WebAuthnCeremonyPurpose: string
{
    case PASSKEY_REGISTRATION = 'PASSKEY_REGISTRATION';
    case PASSKEY_LOGIN = 'PASSKEY_LOGIN';
    case MFA_LOGIN = 'MFA_LOGIN';
    case STEP_UP = 'STEP_UP';
}
