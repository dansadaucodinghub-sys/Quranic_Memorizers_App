<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;

final readonly class PersistedPermission
{
    public function __construct(
        public int $internalId,
        public PermissionId $id,
        public PermissionCode $code,
        public AuthorizationScopeType $scopeType,
        public AuthenticationAssuranceLevel $requiredAssurance,
        public PermissionStatus $status,
    ) {
        if ($internalId < 1) {
            throw new \InvalidArgumentException('Persisted permission is invalid.');
        }
    }
}
