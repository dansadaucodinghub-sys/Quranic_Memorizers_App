<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Domain;

enum GeographyStatus: string
{
    case ACTIVE = 'ACTIVE';
    case RETIRED = 'RETIRED';
}
