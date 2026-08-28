<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentReasonCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Observability\Correlation\CorrelationId;

final readonly class WorkspaceRoleAssignmentCommand
{
    public function __construct(
        public AuthenticatedAccountContext $actor,
        public TenantContext $tenantContext,
        public UuidV7 $targetMembershipId,
        public RoleCode $roleCode,
        public RoleAssignmentReasonCode $reason,
        public CorrelationId $correlationId,
    ) {
        if ($tenantContext->isSystem()) {
            throw new \InvalidArgumentException('Workspace role assignment requires trusted tenant context.');
        }
        if (
            in_array($reason, [
            RoleAssignmentReasonCode::SYSTEM_PLATFORM_BOOTSTRAP,
            RoleAssignmentReasonCode::SYSTEM_WORKSPACE_OWNER_INITIALIZATION,
            ], true)
        ) {
            throw new \InvalidArgumentException('System assignment reason cannot be selected by an account actor.');
        }
    }
}
