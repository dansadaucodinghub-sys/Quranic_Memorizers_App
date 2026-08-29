<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\View;

interface TenantPresentationContext
{
    public function workspacePublicId(): string;

    public function workspaceDisplayName(): string;

    public function tenantContextVersion(): int;
}
