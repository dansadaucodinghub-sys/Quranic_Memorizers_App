<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

/**
 * A server-resolved workspace scope used only while a session-bound exceptional activation is active.
 */
final readonly class PrivilegedWorkspaceAuthorizationScope implements AuthorizationScope
{
    public function __construct(public int $workspaceInternalId)
    {
        if ($workspaceInternalId < 1) {
            throw new \InvalidArgumentException('Privileged workspace authorization scope is invalid.');
        }
    }

    public function type(): AuthorizationScopeType
    {
        return AuthorizationScopeType::WORKSPACE;
    }
}
