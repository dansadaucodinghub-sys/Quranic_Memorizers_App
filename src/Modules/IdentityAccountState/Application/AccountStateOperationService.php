<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Application;

use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccountState\Configuration\AccountStateConfiguration;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateOperationType;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditAppendCommand;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditRecorder;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditStreamIdentity;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventActorKind;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventOutcome;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Application\BaseRoleAuthorizationGuard;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

final readonly class AccountStateOperationService
{
    public function __construct(
        private BaseRoleAuthorizationGuard $authorization,
        private StepUpGuard $stepUp,
        private IdentityRateLimiter $rateLimiter,
        private IdentityFingerprintGenerator $fingerprints,
        private AccountStateRepository $repository,
        private SecurityAuditRecorder $audit,
        private AccountStateSecurityNotificationService $notifications,
        private AccountStateConfiguration $configuration,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function execute(AccountStateOperationCommand $command): AccountStateOperationResult
    {
        if ($command->actor->accountId->toString() === $command->targetAccountId->toString()) {
            throw new \DomainException('Account self-operation is prohibited.');
        }
        $permission = $command->operation === AccountStateOperationType::SUSPEND
            ? 'platform.accounts.suspend' : 'platform.accounts.reactivate';
        $this->authorization->requireAllowed(new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($command->actor),
            new PermissionCode($permission),
            new PlatformAuthorizationScope(),
        ));
        $fingerprint = $this->requestFingerprint($command);
        $attempts = $this->rateAttempts($command);
        if (!$this->rateLimiter->consume($attempts, $this->clock->now())->allowed) {
            throw new \DomainException('Account-state operation is temporarily unavailable.');
        }
        return $this->transactions->transactional(
            fn (): AccountStateOperationResult => $this->executeTransactional($command, $fingerprint),
            TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(1, 0, 0)),
        );
    }

    private function executeTransactional(AccountStateOperationCommand $command, string $fingerprint): AccountStateOperationResult
    {
        $completed = $this->repository->findCompletedSubmission($command->submissionId, $fingerprint);
        if ($completed !== null) {
            return $completed;
        }
        $target = $this->repository->lockAccount($command->targetAccountId);
        if ($target === null) {
            throw new \DomainException('Account-state target is unavailable.');
        }
        $before = $command->operation === AccountStateOperationType::SUSPEND ? 'ACTIVE' : 'SUSPENDED';
        $after = $command->operation === AccountStateOperationType::SUSPEND ? 'SUSPENDED' : 'ACTIVE';
        if ($target['account_status'] !== $before || $target['version'] !== $command->expectedAccountVersion) {
            throw new \DomainException('Account-state operation is stale.');
        }
        if ($command->operation === AccountStateOperationType::SUSPEND && !$this->repository->anotherUsableSecurityAdministratorExists($target['id'])) {
            throw new \DomainException('Last usable security administrator cannot be suspended.');
        }
        $grant = $this->stepUp->consumeWithGrant($command->actor, $command->operation === AccountStateOperationType::SUSPEND
            ? StepUpAction::ACCOUNT_SUSPEND : StepUpAction::ACCOUNT_REACTIVATE);
        $now = $this->clock->now();
        if (!$this->repository->transition($target['id'], $before, $after, $target['version'], $now)) {
            throw new \DomainException('Account-state operation is stale.');
        }
        $this->repository->appendStatusEvent($target['id'], $command->operation === AccountStateOperationType::SUSPEND ? 'SUSPENDED' : 'REACTIVATED', $now);
        if ($command->operation === AccountStateOperationType::SUSPEND) {
            $this->repository->revokeActiveAccess($target['id'], $now);
        }
        $operationId = UuidV7::generate();
        $audit = $this->audit->append(new SecurityAuditAppendCommand(
            new SecurityAuditStreamIdentity(\Qmdb\Modules\SecurityAudit\Domain\SecurityAuditStreamType::ACCOUNT, $command->targetAccountId),
            $command->operation === AccountStateOperationType::SUSPEND ? SecurityEventCode::ACCOUNT_SUSPENDED : SecurityEventCode::ACCOUNT_REACTIVATED,
            SecurityEventOutcome::SUCCESS,
            SecurityEventActorKind::ACCOUNT,
            UuidV7::fromString($command->actor->accountId->toString()),
            UuidV7::fromString($command->actor->sessionId->toString()),
            null,
            SecurityEventSubjectKind::ACCOUNT,
            $command->targetAccountId,
            $command->reason->value,
            null,
            $command->correlationId,
            [
                'operation_public_id' => $operationId->toString(), 'previous_status' => $before, 'new_status' => $after,
                'reason_code' => $command->reason->value, 'target_account_version_before' => $target['version'],
                'target_account_version_after' => $target['version'] + 1,
            ],
            $now,
        ));
        $this->repository->record($operationId, $command, $target['id'], $grant->internalId, $audit->eventPublicId, $target['version'], $now);
        $this->notifications->create(
            $target['id'],
            $command->operation === AccountStateOperationType::SUSPEND
                ? AccountSecurityNotificationType::ACCOUNT_SUSPENDED : AccountSecurityNotificationType::ACCOUNT_REACTIVATED,
            $operationId->toString(),
            $now,
        );

        return new AccountStateOperationResult($operationId->toString(), $command->targetAccountId->toString(), $target['version'] + 1);
    }

    /** @return non-empty-list<IdentityRateLimitAttempt> */
    private function rateAttempts(AccountStateOperationCommand $command): array
    {
        $policy = new IdentityRateLimitPolicy($this->configuration->rateLimitWindowSeconds, $this->configuration->rateLimitMaximumAttempts, $this->configuration->rateLimitWindowSeconds);
        return [
            new IdentityRateLimitAttempt(IdentityRateLimitScope::ACCOUNT_STATE_OPERATION_ACCOUNT, $this->fingerprints->generate('account-state-account', (string) $command->actor->accountInternalId), $policy),
            new IdentityRateLimitAttempt(IdentityRateLimitScope::ACCOUNT_STATE_OPERATION_PEER, $this->fingerprints->generate('account-state-peer', (string) $command->actor->sessionInternalId), $policy),
        ];
    }

    private function requestFingerprint(AccountStateOperationCommand $command): string
    {
        return hash('sha256', implode("\0", [
            $command->operation->value,
            $command->targetAccountId->toString(),
            $command->actor->accountId->toString(),
            $command->reason->value,
            hash('sha256', $command->justification->value),
            $command->reference->value ?? '',
        ]), true);
    }
}
