<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

final readonly class AuthorizationCatalogVerificationReport
{
    /** @param list<string> $errors */
    public function __construct(
        public int $permissionCount,
        public int $roleCount,
        public int $mappingCount,
        public int $platformAssignmentCount,
        public int $workspaceAssignmentCount,
        public array $errors,
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
