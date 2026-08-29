<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentReasonCode;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceRoleAssignmentId;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Shared\Observability\Correlation\CorrelationId;

final readonly class WorkspaceRoleRevocationCommand
{
    public function __construct(
        public AuthenticatedAccountContext $actor,
        public AccountWorkspaceTenantContext $tenantContext,
        public WorkspaceRoleAssignmentId $assignmentId,
        public RoleAssignmentReasonCode $reason,
        public CorrelationId $correlationId,
    ) {
        if ($tenantContext->accountInternalId !== $actor->accountInternalId) {
            throw new \InvalidArgumentException('Workspace role revocation actor does not match Tenant Context.');
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
