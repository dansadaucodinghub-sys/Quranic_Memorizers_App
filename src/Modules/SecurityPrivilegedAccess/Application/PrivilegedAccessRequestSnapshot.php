<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestStatus;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;

/** Safe administrative routing metadata; never contains justification or requested permissions. */
final readonly class PrivilegedAccessRequestSnapshot
{
    public function __construct(
        public PrivilegedAccessRequestId $id,
        public PrivilegedAccessType $type,
        public AuthorizationScopeType $scope,
        public PrivilegedAccessRequestStatus $status,
        public int $subjectAccountInternalId,
        public int $requestedByAccountInternalId,
        public ?int $workspaceInternalId,
        public ?int $subjectMembershipInternalId,
    ) {
    }
}
