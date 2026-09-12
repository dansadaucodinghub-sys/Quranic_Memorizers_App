<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Domain;

enum AppealStatus: string
{
    case SUBMITTED = 'SUBMITTED';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case UPHELD = 'UPHELD';
    case DISMISSED = 'DISMISSED';
    case WITHDRAWN = 'WITHDRAWN';
}
