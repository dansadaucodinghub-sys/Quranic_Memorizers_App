<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

enum PersonProfileSource: string
{
    case SELF_DECLARED = 'SELF_DECLARED';
    case GUARDIAN_DECLARED = 'GUARDIAN_DECLARED';
}
