<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application\Background;

use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Shared\Background\Job\ContextBoundBackgroundJob;
use Qmdb\Shared\Identifier\UuidV7;

interface AccountTenantBoundBackgroundJob extends ContextBoundBackgroundJob
{
    public function accountId(): AccountId;

    public function workspaceId(): WorkspaceId;

    public function membershipId(): UuidV7;
}
