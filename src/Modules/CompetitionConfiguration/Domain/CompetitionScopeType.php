<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionConfiguration\Domain;

enum CompetitionScopeType: string
{
    case NATIONAL = 'NATIONAL';
    case STATE = 'STATE';
    case LGA = 'LGA';
    case ORGANIZATION = 'ORGANIZATION';
    case SCHOOL = 'SCHOOL';
    case OTHER = 'OTHER';
}
