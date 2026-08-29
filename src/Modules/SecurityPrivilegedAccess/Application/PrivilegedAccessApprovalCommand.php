<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessApprovalDecision;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessApprovalType;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessDuration;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReasonCode;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;
use Qmdb\Shared\Observability\Correlation\CorrelationId;

final readonly class PrivilegedAccessApprovalCommand
{
    public function __construct(
        public AuthenticatedAccountContext $actor,
        public PrivilegedAccessRequestId $requestId,
        public PrivilegedAccessApprovalType $approvalType,
        public PrivilegedAccessApprovalDecision $decision,
        public PrivilegedAccessDuration $approvedDuration,
        public PrivilegedAccessReasonCode $reason,
        public CorrelationId $correlationId,
        public ?int $workspaceInternalId = null,
        public ?int $approverMembershipInternalId = null,
    ) {
        if (
            $approvalType === PrivilegedAccessApprovalType::PLATFORM
            && ($workspaceInternalId !== null || $approverMembershipInternalId !== null)
        ) {
            throw new \InvalidArgumentException('Platform approval cannot carry a workspace membership context.');
        }
        if (
            $approvalType === PrivilegedAccessApprovalType::WORKSPACE
            && ($workspaceInternalId === null || $workspaceInternalId < 1
                || $approverMembershipInternalId === null || $approverMembershipInternalId < 1)
        ) {
            throw new \InvalidArgumentException('Workspace approval requires one active approver membership.');
        }
    }

    public function scope(): AuthorizationScopeType
    {
        return $this->approvalType === PrivilegedAccessApprovalType::PLATFORM
            ? AuthorizationScopeType::PLATFORM
            : AuthorizationScopeType::WORKSPACE;
    }
}
