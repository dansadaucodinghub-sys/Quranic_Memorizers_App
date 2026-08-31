<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Domain;

enum GeographyDatasetStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUPERSEDED = 'SUPERSEDED';
}
