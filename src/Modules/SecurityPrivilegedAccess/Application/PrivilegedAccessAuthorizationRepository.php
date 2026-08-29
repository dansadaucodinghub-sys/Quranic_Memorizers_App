<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use DateTimeImmutable;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationDecisionSource;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;

interface PrivilegedAccessAuthorizationRepository
{
    public function activeSource(
        AuthorizationSubject $subject,
        PermissionCode $permission,
        AuthorizationScopeType $scope,
        ?int $workspaceInternalId,
        DateTimeImmutable $now,
    ): ?AuthorizationDecisionSource;
}
