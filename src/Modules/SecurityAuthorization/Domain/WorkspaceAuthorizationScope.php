<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;

final readonly class WorkspaceAuthorizationScope implements AuthorizationScope
{
    public function __construct(public AccountWorkspaceTenantContext $tenantContext)
    {
    }

    public function type(): AuthorizationScopeType
    {
        return AuthorizationScopeType::WORKSPACE;
    }
}
