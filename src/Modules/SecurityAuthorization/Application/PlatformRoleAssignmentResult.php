<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\SecurityAuthorization\Domain\PlatformRoleAssignmentId;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentStatus;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentVersion;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;

final readonly class PlatformRoleAssignmentResult
{
    public function __construct(
        public PlatformRoleAssignmentId $assignmentId,
        public RoleCode $roleCode,
        public RoleAssignmentStatus $status,
        public RoleAssignmentVersion $version,
    ) {
    }
}
