<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

enum PersonNameType: string
{
    case PRIMARY = 'PRIMARY';
    case PREFERRED = 'PREFERRED';
    case ARABIC = 'ARABIC';
    case ALIAS = 'ALIAS';
}
