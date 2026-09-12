<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionJudging\Domain;

enum JudgeAssignmentStatus: string
{
    case INVITED = 'INVITED';
    case ACCEPTED = 'ACCEPTED';
    case DECLINED = 'DECLINED';
    case REVOKED = 'REVOKED';
    case COMPLETED = 'COMPLETED';
}
