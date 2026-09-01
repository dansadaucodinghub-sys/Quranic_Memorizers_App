<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

enum PersonNameStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUPERSEDED = 'SUPERSEDED';
}
