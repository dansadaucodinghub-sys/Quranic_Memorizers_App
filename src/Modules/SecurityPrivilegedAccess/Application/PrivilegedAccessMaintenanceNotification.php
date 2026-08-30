<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;

/** Safe, post-state notification intent created by one bounded maintenance transition. */
final readonly class PrivilegedAccessMaintenanceNotification
{
    public function __construct(
        public int $accountInternalId,
        public AccountSecurityNotificationType $type,
        public string $requestPublicId,
        public AuthorizationScopeType $scope = AuthorizationScopeType::PLATFORM,
        public ?string $workspacePublicId = null,
    ) {
        if (
            $accountInternalId < 1
            || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $requestPublicId)
        ) {
            throw new \InvalidArgumentException('Privileged-access maintenance notification is invalid.');
        }
    }
}
