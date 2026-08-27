<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

enum WebAuthnCeremonyStatus: string
{
    case PENDING = 'PENDING';
    case CONSUMED = 'CONSUMED';
    case EXPIRED = 'EXPIRED';
    case REVOKED = 'REVOKED';
}
