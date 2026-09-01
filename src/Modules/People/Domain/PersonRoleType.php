<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

enum PersonRoleType: string
{
    case MEMORIZER = 'MEMORIZER';
    case RECITER = 'RECITER';
    case COMPETITOR = 'COMPETITOR';
    case GUARDIAN = 'GUARDIAN';
}
