<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\TenancyContext;

use DateTimeImmutable;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\Tenancy\Domain\MembershipStatus;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\Tenancy\Domain\WorkspaceStatus;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Modules\TenancyContext\Domain\ResolvedWorkspaceMembershipIdentity;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class AccountWorkspaceTenantContextFactory
{
    public static function create(
        AuthenticatedAccountContext $account,
        int $workspaceInternalId,
        ?WorkspaceId $workspaceId = null,
        int $membershipInternalId = 43,
        ?UuidV7 $membershipId = null,
        int $tenantContextVersion = 1,
    ): AccountWorkspaceTenantContext {
        return AccountWorkspaceTenantContext::trusted(
            $account->accountInternalId,
            $account->accountId,
            $account->sessionInternalId,
            $account->sessionId,
            $workspaceInternalId,
            $workspaceId ?? WorkspaceId::generate(),
            WorkspaceStatus::ACTIVE,
            1,
            ResolvedWorkspaceMembershipIdentity::trusted(
                $membershipInternalId,
                $membershipId ?? UuidV7::generate(),
                $workspaceInternalId,
                $account->accountInternalId,
                MembershipStatus::ACTIVE,
                1,
            ),
            'Verified Workspace',
            new TenantContextVersion($tenantContextVersion),
            new DateTimeImmutable('2026-08-28T12:00:00Z'),
        );
    }
}
