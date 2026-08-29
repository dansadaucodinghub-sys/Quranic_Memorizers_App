<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\TenancyContext\Application\Exception\StaleTenantContextException;
use Qmdb\Modules\TenancyContext\Application\Exception\WorkspaceContextUnavailableException;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Modules\TenancyContext\Domain\Repository\SessionTenantContextRepository;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Time\Clock;

final readonly class WorkspaceContextSelectionService
{
    public function __construct(
        private SessionTenantContextRepository $repository,
        private TransactionManager $transactions,
        private Clock $clock,
        private ?EventLogger $logger = null,
    ) {
    }

    public function select(
        AuthenticatedAccountContext $account,
        WorkspaceId $workspaceId,
        TenantContextVersion $expectedVersion,
    ): AccountWorkspaceTenantContext {
        return $this->execute(new WorkspaceContextSelectionCommand(
            $account,
            $workspaceId,
            $expectedVersion,
        ))->context;
    }

    public function execute(WorkspaceContextSelectionCommand $command): WorkspaceContextSelectionResult
    {
        $account = $command->account;
        $workspaceId = $command->workspaceId;
        $expectedVersion = $command->expectedVersion;
        [$context, $changed] = $this->transactions->transactional(
            function () use ($command, $account, $workspaceId, $expectedVersion): array {
                $state = $this->repository->state($account, true);
                if ($state->version->value !== $expectedVersion->value) {
                    $this->logStale($command);
                    throw new StaleTenantContextException();
                }
                if ($state->context?->workspaceId->toString() === $workspaceId->toString()) {
                    return [$state->context, false];
                }
                $workspace = $this->repository->selectable($account, $workspaceId, true);
                if ($workspace === null) {
                    throw new WorkspaceContextUnavailableException();
                }
                $selectedAt = $this->clock->now();
                if (!$this->repository->select($account, $workspace, $expectedVersion, $selectedAt)) {
                    throw new StaleTenantContextException();
                }

                return [AccountWorkspaceTenantContext::trusted(
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
                    new TenantContextVersion($expectedVersion->value + 1),
                    $selectedAt,
                ), true];
            },
        );
        if ($changed) {
            $logContext = [
                'actor_account_public_id' => $account->accountId->toString(),
                'session_public_id' => $account->sessionId->toString(),
                'workspace_public_id' => $context->workspaceId->toString(),
                'membership_public_id' => $context->membership->membershipId->toString(),
                'tenant_context_version' => $context->version->value,
            ];
            if ($command->correlationId !== null) {
                $logContext['request_id'] = $command->correlationId->value();
            }
            $this->logger?->log(LogLevel::NOTICE, new LogEventName('tenancy.context.selected'), $logContext);
        }

        return new WorkspaceContextSelectionResult($context, $changed);
    }

    private function logStale(WorkspaceContextSelectionCommand $command): void
    {
        $context = [
            'actor_account_public_id' => $command->account->accountId->toString(),
            'session_public_id' => $command->account->sessionId->toString(),
            'tenant_context_version' => $command->expectedVersion->value,
            'reason_code' => 'EXPECTED_VERSION_MISMATCH',
        ];
        if ($command->correlationId !== null) {
            $context['request_id'] = $command->correlationId->value();
        }
        $this->logger?->log(LogLevel::WARNING, new LogEventName('tenancy.context.stale'), $context);
    }
}
