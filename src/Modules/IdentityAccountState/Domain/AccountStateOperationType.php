<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Domain;

enum AccountStateOperationType: string
{
    case SUSPEND = 'SUSPEND';
    case REACTIVATE = 'REACTIVATE';
}
