<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

enum MemorizerProgressStatus: string
{
    case NOT_RECORDED = 'NOT_RECORDED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETE = 'COMPLETE';
    case MAINTENANCE = 'MAINTENANCE';
}
