<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class AuthorizationMembershipRecord
{
    public function __construct(
        public int $internalId,
        public UuidV7 $id,
        public int $workspaceInternalId,
        public int $accountInternalId,
        public AccountId $accountId,
        public bool $active,
    ) {
        if ($internalId < 1 || $workspaceInternalId < 1 || $accountInternalId < 1) {
            throw new \InvalidArgumentException('Authorization membership record is invalid.');
        }
    }
}
