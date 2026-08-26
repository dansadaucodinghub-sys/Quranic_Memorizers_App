<?php

declare(strict_types=1);

namespace Qmdb\Modules\Identity\Domain;

enum CredentialType: string
{
    case PASSWORD = 'PASSWORD';
}
