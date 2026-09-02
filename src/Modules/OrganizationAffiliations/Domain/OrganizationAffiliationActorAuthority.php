<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Domain;

enum OrganizationAffiliationActorAuthority: string
{
    case ORGANIZATION_MANAGER = 'ORGANIZATION_MANAGER';
    case SELF = 'SELF';
    case GUARDIAN = 'GUARDIAN';
    case SYSTEM = 'SYSTEM';
}
