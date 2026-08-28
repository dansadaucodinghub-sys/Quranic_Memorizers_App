<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentReasonCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Shared\Observability\Correlation\CorrelationId;

final readonly class PlatformRoleAssignmentCommand
{
    public function __construct(
        public AuthenticatedAccountContext $actor,
        public AccountId $targetAccountId,
        public RoleCode $roleCode,
        public RoleAssignmentReasonCode $reason,
        public CorrelationId $correlationId,
    ) {
        self::assertAccountReason($reason);
    }

    private static function assertAccountReason(RoleAssignmentReasonCode $reason): void
    {
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
