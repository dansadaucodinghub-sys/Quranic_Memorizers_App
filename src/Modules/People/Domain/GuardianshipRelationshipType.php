<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

enum GuardianshipRelationshipType: string
{
    case PARENT = 'PARENT';
    case LEGAL_GUARDIAN = 'LEGAL_GUARDIAN';
    case CAREGIVER = 'CAREGIVER';
    case OTHER = 'OTHER';
}
