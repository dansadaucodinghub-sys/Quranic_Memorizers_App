<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\TenancyContext\Domain\Repository\SessionTenantContextRepository;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Time\Clock;

final readonly class SessionTenantContextResolver
{
    public function __construct(
        private SessionTenantContextRepository $repository,
        private TransactionManager $transactions,
        private Clock $clock,
        private ?EventLogger $logger = null,
    ) {
    }

    public function resolve(AuthenticatedAccountContext $account): TenantContextResolution
    {
        $state = $this->repository->state($account);
        if (!$state->hasStoredSelection || $state->context !== null) {
            return new TenantContextResolution($state->version, $state->context);
        }

        $resolution = $this->transactions->transactional(function () use ($account): TenantContextResolution {
            $locked = $this->repository->state($account, true);
            if (!$locked->hasStoredSelection || $locked->context !== null) {
                return new TenantContextResolution($locked->version, $locked->context);
            }
            $cleared = $this->repository->clear($account, $locked->version, $this->clock->now());
            if (!$cleared) {
                $fresh = $this->repository->state($account);

                return new TenantContextResolution($fresh->version, $fresh->context);
            }

            return new TenantContextResolution(
                new TenantContextVersion($locked->version->value + 1),
                null,
                true,
            );
        });
        if ($resolution->invalidSelectionCleared) {
            $this->logger?->log(LogLevel::WARNING, new LogEventName('tenancy.context.invalidated'), [
                'actor_account_public_id' => $account->accountId->toString(),
                'session_public_id' => $account->sessionId->toString(),
                'tenant_context_version' => $resolution->version->value,
                'reason_code' => 'SELECTED_CONTEXT_INACTIVE',
            ]);
        }

        return $resolution;
    }
}
