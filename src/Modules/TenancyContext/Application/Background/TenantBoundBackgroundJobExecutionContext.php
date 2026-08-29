<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application\Background;

use JsonSerializable;
use LogicException;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class TenantBoundBackgroundJobExecutionContext implements JsonSerializable
{
    public function __construct(
        public int $accountInternalId,
        public AccountId $accountId,
        public int $workspaceInternalId,
        public WorkspaceId $workspaceId,
        public int $membershipInternalId,
        public UuidV7 $membershipId,
    ) {
        if ($accountInternalId < 1 || $workspaceInternalId < 1 || $membershipInternalId < 1) {
            throw new \InvalidArgumentException('Tenant-bound background execution context is invalid.');
        }
    }

    public function tenant(): TenantContext
    {
        return TenantContext::trusted($this->workspaceInternalId, $this->workspaceId);
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('Tenant-bound background context cannot be serialized.');
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw new LogicException('Tenant-bound background context cannot be serialized.');
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return [
            'account_id' => '[redacted]',
            'workspace_id' => '[redacted]',
            'membership_id' => '[redacted]',
        ];
    }
}
