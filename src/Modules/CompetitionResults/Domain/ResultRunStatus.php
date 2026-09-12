<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Domain;

enum ResultRunStatus: string
{
    case CALCULATED = 'CALCULATED';
    case VERIFIED = 'VERIFIED';
    case PUBLISHED = 'PUBLISHED';
    case SUPERSEDED = 'SUPERSEDED';
}
