<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

use Qmdb\Modules\TenancyContext\Domain\Repository\SessionTenantContextRepository;

final readonly class AccountWorkspaceInventoryHandler
{
    public function __construct(
        private SessionTenantContextResolver $resolver,
        private SessionTenantContextRepository $repository,
    ) {
    }

    public function handle(AccountWorkspaceInventoryQuery $query): AccountWorkspaceInventory
    {
        $resolved = $this->resolver->resolve($query->account);
        $available = $this->repository->availableForAccount(
            $query->account,
            $query->pageSize + 1,
            $query->afterWorkspaceId,
        );
        $hasNext = count($available) > $query->pageSize;
        if ($hasNext) {
            array_pop($available);
        }
        $items = array_map(
            static fn ($workspace): AccountWorkspaceInventoryItem => new AccountWorkspaceInventoryItem(
                $workspace->workspaceId->toString(),
                $workspace->workspaceName,
                $workspace->workspaceStatus->value,
                $workspace->membershipId->toString(),
                $workspace->membershipStatus->value,
                $resolved->context?->workspaceId->toString() === $workspace->workspaceId->toString(),
                $workspace->workspaceUpdatedAt,
            ),
            $available,
        );
        $last = $available === [] ? null : $available[array_key_last($available)];

        return new AccountWorkspaceInventory(
            $resolved->version,
            $resolved->context,
            $items,
            $hasNext && $last !== null ? $last->workspaceId->toString() : null,
        );
    }
}
