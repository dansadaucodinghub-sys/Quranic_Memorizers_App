<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentStatus;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentVersion;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceRoleAssignmentId;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;

final readonly class WorkspaceRoleAssignmentResult
{
    public function __construct(
        public WorkspaceRoleAssignmentId $assignmentId,
        public RoleCode $roleCode,
        public RoleAssignmentStatus $status,
        public RoleAssignmentVersion $version,
        public WorkspaceId $workspaceId,
    ) {
    }
}
