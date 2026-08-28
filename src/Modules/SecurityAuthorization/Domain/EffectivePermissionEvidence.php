<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

final readonly class EffectivePermissionEvidence
{
    public function __construct(
        public bool $activeAssignmentExists,
        public bool $activeRoleExists,
        public bool $mappedPermissionExists,
    ) {
    }

    public function allows(): bool
    {
        return $this->activeAssignmentExists && $this->activeRoleExists && $this->mappedPermissionExists;
    }
}
