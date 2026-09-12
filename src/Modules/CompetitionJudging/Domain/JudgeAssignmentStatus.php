<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionJudging\Domain;

enum JudgeAssignmentStatus: string
{
    case ASSIGNED = 'ASSIGNED';
    case ACCEPTED = 'ACCEPTED';
    case DECLINED = 'DECLINED';
    case REVOKED = 'REVOKED';
    case COMPLETED = 'COMPLETED';
}
