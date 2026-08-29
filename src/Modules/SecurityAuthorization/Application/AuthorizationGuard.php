<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException;

final readonly class AuthorizationGuard implements AuthorizationRequirementGuard
{
    public function __construct(private AuthorizationService $authorization)
    {
    }

    public function requireAllowed(AuthorizationRequest $request): void
    {
        if (!$this->authorization->decide($request)->isAllowed()) {
            throw new AuthorizationDeniedException();
        }
    }
}
