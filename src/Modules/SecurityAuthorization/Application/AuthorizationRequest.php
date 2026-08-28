<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScope;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;

final readonly class AuthorizationRequest
{
    public function __construct(
        public AuthorizationSubject $subject,
        public PermissionCode $permission,
        public AuthorizationScope $scope,
    ) {
    }
}
