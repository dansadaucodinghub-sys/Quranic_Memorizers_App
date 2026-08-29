<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\TenancyContext\Application\Exception\StaleTenantContextException;
use Qmdb\Modules\TenancyContext\Domain\Repository\SessionTenantContextRepository;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Time\Clock;

final readonly class WorkspaceContextClearingService
{
    public function __construct(
        private SessionTenantContextRepository $repository,
        private TransactionManager $transactions,
        private Clock $clock,
        private ?EventLogger $logger = null,
    ) {
    }

    public function clear(
        AuthenticatedAccountContext $account,
        TenantContextVersion $expectedVersion,
    ): TenantContextVersion {
        return $this->execute(new WorkspaceContextClearingCommand($account, $expectedVersion))->version;
    }

    public function execute(WorkspaceContextClearingCommand $command): WorkspaceContextClearingResult
    {
        $account = $command->account;
        $expectedVersion = $command->expectedVersion;
        [$version, $changed] = $this->transactions->transactional(function () use ($command): array {
            $account = $command->account;
            $expectedVersion = $command->expectedVersion;
            $state = $this->repository->state($account, true);
            if (
                $state->version->value !== $expectedVersion->value
            ) {
                $this->logStale($command);
                throw new StaleTenantContextException();
            }
            if (!$state->hasStoredSelection) {
                return [$state->version, false];
            }
            if (!$this->repository->clear($account, $expectedVersion, $this->clock->now())) {
                throw new StaleTenantContextException();
            }

            return [new TenantContextVersion($expectedVersion->value + 1), true];
        });
        if ($changed) {
            $context = [
                'actor_account_public_id' => $account->accountId->toString(),
                'session_public_id' => $account->sessionId->toString(),
                'tenant_context_version' => $version->value,
            ];
            if ($command->correlationId !== null) {
                $context['request_id'] = $command->correlationId->value();
            }
            $this->logger?->log(LogLevel::NOTICE, new LogEventName('tenancy.context.cleared'), $context);
        }

        return new WorkspaceContextClearingResult($version, $changed);
    }

    private function logStale(WorkspaceContextClearingCommand $command): void
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
