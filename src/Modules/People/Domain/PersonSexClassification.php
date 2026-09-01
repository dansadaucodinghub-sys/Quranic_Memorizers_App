<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

enum PersonSexClassification: string
{
    case MALE = 'MALE';
    case FEMALE = 'FEMALE';
    case NOT_RECORDED = 'NOT_RECORDED';
}
