<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentReasonCode;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceRoleAssignmentId;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Shared\Observability\Correlation\CorrelationId;

final readonly class WorkspaceRoleRevocationCommand
{
    public function __construct(
        public AuthenticatedAccountContext $actor,
        public TenantContext $tenantContext,
        public WorkspaceRoleAssignmentId $assignmentId,
        public RoleAssignmentReasonCode $reason,
        public CorrelationId $correlationId,
    ) {
        if ($tenantContext->isSystem()) {
            throw new \InvalidArgumentException('Workspace role revocation requires trusted tenant context.');
        }
        if (
            in_array($reason, [
            RoleAssignmentReasonCode::SYSTEM_PLATFORM_BOOTSTRAP,
            RoleAssignmentReasonCode::SYSTEM_WORKSPACE_OWNER_INITIALIZATION,
            ], true)
        ) {
            throw new \InvalidArgumentException('System revocation reason cannot be selected by an account actor.');
        }
    }
}
