<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\TenancyContext;

use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\TenancyContext\Application\Background\AccountTenantBoundBackgroundJob;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class TestAccountTenantBoundBackgroundJob implements AccountTenantBoundBackgroundJob
{
    public function __construct(
        private AccountId $accountId,
        private WorkspaceId $workspaceId,
        private UuidV7 $membershipId,
    ) {
    }

    public function accountId(): AccountId
    {
        return $this->accountId;
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function membershipId(): UuidV7
    {
        return $this->membershipId;
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['tenant_identifiers' => '[redacted]'];
    }
}
