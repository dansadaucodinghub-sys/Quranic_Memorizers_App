<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReviewId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReviewStatus;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;

/** Safe review routing metadata. The review summary remains unavailable until authorized completion. */
final readonly class PrivilegedAccessReviewSnapshot
{
    public function __construct(
        public PrivilegedAccessReviewId $id,
        public PrivilegedAccessReviewStatus $status,
        public PrivilegedAccessType $type,
        public int $subjectAccountInternalId,
        public AuthorizationScopeType $scope = AuthorizationScopeType::PLATFORM,
        public ?string $workspacePublicId = null,
    ) {
    }
}
