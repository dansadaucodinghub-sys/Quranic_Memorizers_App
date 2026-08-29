<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\TenancyContext;

use DateTimeImmutable;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Modules\TenancyContext\Domain\Repository\SessionTenantContextRepository;
use Qmdb\Modules\TenancyContext\Domain\SessionTenantContextState;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;
use Qmdb\Modules\TenancyContext\Domain\WorkspaceContextOption;

final class InMemorySessionTenantContextRepository implements SessionTenantContextRepository
{
    /** @param list<WorkspaceContextOption> $available */
    public function __construct(
        public SessionTenantContextState $currentState,
        public array $available = [],
    ) {
    }

    public function state(AuthenticatedAccountContext $account, bool $forUpdate = false): SessionTenantContextState
    {
        if (
            $account->accountInternalId !== $this->currentState->accountInternalId
            || $account->sessionInternalId !== $this->currentState->sessionInternalId
        ) {
            throw new \UnexpectedValueException('Test tenant-context account or session mismatch.');
        }

        return $this->currentState;
    }

    public function availableForAccount(
        AuthenticatedAccountContext $account,
        int $limit = 50,
        ?WorkspaceId $afterWorkspaceId = null,
    ): array {
        $result = array_values(array_filter(
            $this->available,
            static fn (WorkspaceContextOption $option): bool => $afterWorkspaceId === null
                || strcmp($option->workspaceId->toString(), $afterWorkspaceId->toString()) > 0,
        ));

        return array_slice($result, 0, $limit);
    }

    public function selectable(
        AuthenticatedAccountContext $account,
        WorkspaceId $workspaceId,
        bool $forUpdate = false,
    ): ?WorkspaceContextOption {
        foreach ($this->available as $option) {
            if ($option->workspaceId->toString() === $workspaceId->toString()) {
                return $option;
            }
        }

        return null;
    }

    public function select(
        AuthenticatedAccountContext $account,
        WorkspaceContextOption $workspace,
        TenantContextVersion $expectedVersion,
        DateTimeImmutable $selectedAt,
    ): bool {
        if ($this->currentState->version->value !== $expectedVersion->value) {
            return false;
        }
        $version = new TenantContextVersion($expectedVersion->value + 1);
        $context = AccountWorkspaceTenantContext::trusted(
            $account->accountInternalId,
            $account->accountId,
            $account->sessionInternalId,
            $account->sessionId,
            $workspace->workspaceInternalId,
            $workspace->workspaceId,
            $workspace->workspaceStatus,
            $workspace->workspaceVersion,
            $workspace->membershipIdentity($account->accountInternalId),
            $workspace->workspaceName,
            $version,
            $selectedAt,
        );
        $this->currentState = new SessionTenantContextState(
            $account->sessionInternalId,
            $account->accountInternalId,
            $workspace->workspaceInternalId,
            $workspace->membershipInternalId,
            $version,
            $selectedAt,
            $this->currentState->sessionStatus,
            $this->currentState->sessionVersion,
            true,
            $context,
        );

        return true;
    }

    public function clear(
        AuthenticatedAccountContext $account,
        TenantContextVersion $expectedVersion,
        DateTimeImmutable $updatedAt,
    ): bool {
        if ($this->currentState->version->value !== $expectedVersion->value) {
            return false;
        }
        $this->currentState = new SessionTenantContextState(
            $account->sessionInternalId,
            $account->accountInternalId,
            null,
            null,
            new TenantContextVersion($expectedVersion->value + 1),
            null,
            $this->currentState->sessionStatus,
            $this->currentState->sessionVersion,
            false,
            null,
        );

        return true;
    }
}
