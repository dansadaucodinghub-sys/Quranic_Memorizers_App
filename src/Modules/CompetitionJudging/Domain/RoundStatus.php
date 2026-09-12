<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionJudging\Domain;

/** The authoritative state of a round; mutations must use RoundLifecycle. */
enum RoundStatus: string
{
    case DRAFT = 'DRAFT';
    case READY = 'READY';
    case SCORING_OPEN = 'SCORING_OPEN';
    case SCORING_CLOSED = 'SCORING_CLOSED';
    case RESULTS_CALCULATED = 'RESULTS_CALCULATED';
    case RESULTS_VERIFIED = 'RESULTS_VERIFIED';
    case RESULTS_PUBLISHED = 'RESULTS_PUBLISHED';
    case CANCELLED = 'CANCELLED';
}
