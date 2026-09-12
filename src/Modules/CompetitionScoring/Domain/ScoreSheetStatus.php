<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionScoring\Domain;

enum ScoreSheetStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case LOCKED = 'LOCKED';
    case SUPERSEDED = 'SUPERSEDED';
    case VOIDED = 'VOIDED';
}
