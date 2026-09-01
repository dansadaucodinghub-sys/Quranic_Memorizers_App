<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

enum PersonProfileAccessReason: string
{
    case SELF_LINK_REQUIRED = 'SELF_LINK_REQUIRED';
    case PERSON_UNAVAILABLE = 'PERSON_UNAVAILABLE';
    case GUARDIAN_ROLE_REQUIRED = 'GUARDIAN_ROLE_REQUIRED';
    case GUARDIANSHIP_REQUIRED = 'GUARDIANSHIP_REQUIRED';
}
