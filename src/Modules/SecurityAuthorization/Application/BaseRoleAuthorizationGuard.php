<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException;

/**
 * Guard for security-administration operations that must never use an exceptional access source.
 */
final readonly class BaseRoleAuthorizationGuard implements AuthorizationRequirementGuard
{
    public function __construct(private RoleBasedAuthorizationService $authorization)
    {
    }

    public function requireAllowed(AuthorizationRequest $request): void
    {
        if (!$this->authorization->decide($request)->isAllowed()) {
            throw new AuthorizationDeniedException();
        }
    }
}
