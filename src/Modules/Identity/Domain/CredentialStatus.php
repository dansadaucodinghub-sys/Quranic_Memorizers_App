<?php

declare(strict_types=1);

namespace Qmdb\Modules\Identity\Domain;

enum CredentialStatus: string
{
    case ACTIVE = 'ACTIVE';
    case REVOKED = 'REVOKED';
}
