<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use Qmdb\Modules\Tenancy\Application\TenantContext;

final readonly class WorkspaceAuthorizationScope implements AuthorizationScope
{
    public function __construct(public TenantContext $tenantContext)
    {
        if ($tenantContext->isSystem()) {
            throw new \InvalidArgumentException('Workspace authorization requires trusted tenant context.');
        }
    }

    public function type(): AuthorizationScopeType
    {
        return AuthorizationScopeType::WORKSPACE;
    }
}
