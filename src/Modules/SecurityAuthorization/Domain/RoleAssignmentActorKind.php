<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

enum RoleAssignmentActorKind: string
{
    case ACCOUNT = 'ACCOUNT';
    case SYSTEM = 'SYSTEM';

    public function assertActor(?int $accountInternalId): void
    {
        if (($this === self::ACCOUNT) !== ($accountInternalId !== null && $accountInternalId > 0)) {
            throw new \DomainException('Role-assignment actor relationship is invalid.');
        }
    }
}
