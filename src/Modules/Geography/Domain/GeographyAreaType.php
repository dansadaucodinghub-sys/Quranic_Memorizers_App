<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Domain;

enum GeographyAreaType: string
{
    case STATE = 'STATE';
    case FEDERAL_CAPITAL_TERRITORY = 'FEDERAL_CAPITAL_TERRITORY';
    case LOCAL_GOVERNMENT_AREA = 'LOCAL_GOVERNMENT_AREA';
    case AREA_COUNCIL = 'AREA_COUNCIL';

    public function level(): int
    {
        return match ($this) {
            self::STATE, self::FEDERAL_CAPITAL_TERRITORY => 1,
            self::LOCAL_GOVERNMENT_AREA, self::AREA_COUNCIL => 2,
        };
    }
}
