<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

interface AuthorizationRequirementGuard
{
    public function requireAllowed(AuthorizationRequest $request): void;
}
