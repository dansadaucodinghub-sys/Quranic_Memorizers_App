<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessApprovalDecision;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestStatus;

final readonly class PrivilegedAccessApprovalResult
{
    public function __construct(
        public PrivilegedAccessRequestId $requestId,
        public PrivilegedAccessApprovalDecision $decision,
        public PrivilegedAccessRequestStatus $requestStatus,
        public ?int $approvedDurationSeconds,
    ) {
    }
}
