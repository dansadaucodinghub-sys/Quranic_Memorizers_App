<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

final readonly class AuthorizationRoleRecord
{
    public function __construct(
        public int $internalId,
        public RoleId $id,
        public RoleCode $code,
        public AuthorizationScopeType $scopeType,
        public RoleStatus $status,
    ) {
        if ($internalId < 1) {
            throw new \InvalidArgumentException('Authorization role record is invalid.');
        }
    }
}
