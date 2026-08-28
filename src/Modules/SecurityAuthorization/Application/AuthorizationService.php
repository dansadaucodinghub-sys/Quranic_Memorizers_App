<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

interface AuthorizationService
{
    public function decide(AuthorizationRequest $request): AuthorizationDecision;
}
