<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

enum PersonGeographyAssociationType: string
{
    case ORIGIN = 'ORIGIN';
    case RESIDENCE = 'RESIDENCE';
}
