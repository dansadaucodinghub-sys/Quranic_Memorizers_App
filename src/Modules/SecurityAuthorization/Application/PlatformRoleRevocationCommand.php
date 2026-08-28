<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformRoleAssignmentId;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentReasonCode;
use Qmdb\Shared\Observability\Correlation\CorrelationId;

final readonly class PlatformRoleRevocationCommand
{
    public function __construct(
        public AuthenticatedAccountContext $actor,
        public AccountId $targetAccountId,
        public PlatformRoleAssignmentId $assignmentId,
        public RoleAssignmentReasonCode $reason,
        public CorrelationId $correlationId,
    ) {
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
