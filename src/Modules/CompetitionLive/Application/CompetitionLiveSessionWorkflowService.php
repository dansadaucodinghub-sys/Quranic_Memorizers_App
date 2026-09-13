<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Application;

use Qmdb\Modules\CompetitionLive\Domain\LiveSessionLifecycle;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

/**
 * The sole P7 live-session mutation gateway. Browser, CLI, and scheduled
 * callers receive the same authorization, rate, assurance, audit, ordering,
 * and idempotency controls before an authoritative event is appended.
 */
final readonly class CompetitionLiveSessionWorkflowService
{
    public function __construct(
        private CompetitionLiveRuntimeRepository $repository,
        private LiveSessionLifecycle $lifecycle,
        private AuthorizationRequirementGuard $authorization,
        private StepUpGuard $stepUp,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    /** @return array{status:string,version:int,sequence:int,replayed:bool} */
    public function operate(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submissionId,
        string $operation,
        UuidV7 $sessionPublicId,
        int $expectedVersion,
        ?string $correlationId = null,
    ): array {
        if ($expectedVersion < 1) {
            throw new \InvalidArgumentException('Live session version is invalid.');
        }
        $rule = $this->rule($operation);
        $this->authorization->requireAllowed(new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($actor),
            new PermissionCode('workspace.competitions.operate_live'),
            new WorkspaceAuthorizationScope($tenant),
        ));
        $this->rate($actor);

        return $this->transactions->transactional(
            fn (): array => $this->inTransaction($actor, $tenant, $submissionId, $rule, $sessionPublicId, $expectedVersion, $correlationId),
            TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)),
        );
    }

    /**
     * @param array{code:string,target:string,step_up:?StepUpAction,audit:SecurityEventCode} $rule
     * @return array{status:string,version:int,sequence:int,replayed:bool}
     */
    private function inTransaction(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submissionId,
        array $rule,
        UuidV7 $sessionPublicId,
        int $expectedVersion,
        ?string $correlationId,
    ): array {
        $fingerprint = $this->fingerprints->generate('competition-p7-live-session', implode("\0", [$tenant->workspaceInternalId, $actor->accountInternalId, $rule['code'], $sessionPublicId->toString(), $expectedVersion]));
        $completed = $this->repository->completed($submissionId, $fingerprint->toBinary());
        if ($completed !== null) {
            return ['status' => $completed['status'], 'version' => $completed['version'], 'sequence' => $completed['sequence'], 'replayed' => true];
        }
        $session = $this->repository->lockSession($tenant->workspaceInternalId, $sessionPublicId);
        if ($session === null || $session['version'] !== $expectedVersion) {
            throw new \DomainException('Live session is stale or unavailable.');
        }
        $this->lifecycle->assertTransition($session['status'], $rule['target']);
        if ($rule['step_up'] !== null) {
            $this->stepUp->consumeWithGrant($actor, $rule['step_up']);
        }
        $now = $this->clock->now();
        if (!$this->repository->transitionSession($session, $rule['target'], $now)) {
            throw new \DomainException('Live session changed concurrently.');
        }
        $eventType = $this->eventType($session['status'], $rule['target']);
        $eventCorrelationId = $correlationId === null ? UuidV7::generate() : UuidV7::fromString($correlationId);
        $sequence = $this->repository->appendEvent($session, $eventType, 'WORKSPACE_PRIVATE', ['session_public_id' => $session['public_id'], 'operation' => $rule['code'], 'previous_status' => $session['status'], 'status' => $rule['target']], $actor->accountInternalId, $eventCorrelationId, $now);
        $this->audit->workspace($rule['audit'], $tenant->workspacePublicId(), SecurityEventSubjectKind::COMPETITION_LIVE_SESSION, $session['public_id'], $actor->accountId->toString(), $now, ['operation' => $rule['code'], 'previous_status' => $session['status'], 'new_status' => $rule['target'], 'version_before' => $session['version'], 'version_after' => $session['version'] + 1, 'sequence' => $sequence], null, $eventCorrelationId->toString());
        $this->repository->record($submissionId, $fingerprint->toBinary(), $rule['code'], $session, $rule['target'], $session['version'] + 1, $sequence, $now);

        return ['status' => $rule['target'], 'version' => $session['version'] + 1, 'sequence' => $sequence, 'replayed' => false];
    }

    private function rate(AuthenticatedAccountContext $actor): void
    {
        $attempt = new IdentityRateLimitAttempt(
            IdentityRateLimitScope::COMPETITION_LIVE_OPERATION_ACCOUNT,
            $this->fingerprints->generate('competition-p7-live-account', (string) $actor->accountInternalId),
            new IdentityRateLimitPolicy(60, 20, 60),
        );
        if (!$this->rateLimits->consume([$attempt], $this->clock->now())->allowed) {
            throw new \DomainException('Live operation is temporarily unavailable.');
        }
    }

    /** @return array{code:string,target:string,step_up:?StepUpAction,audit:SecurityEventCode} */
    private function rule(string $operation): array
    {
        return match ($operation) {
            'COMPETITION_LIVE_SESSION_OPEN' => ['code' => $operation, 'target' => 'OPEN', 'step_up' => null, 'audit' => SecurityEventCode::COMPETITION_LIVE_SESSION_OPENED],
            'COMPETITION_LIVE_SESSION_PAUSE' => ['code' => $operation, 'target' => 'PAUSED', 'step_up' => null, 'audit' => SecurityEventCode::COMPETITION_LIVE_SESSION_PAUSED],
            'COMPETITION_LIVE_SESSION_RESUME' => ['code' => $operation, 'target' => 'OPEN', 'step_up' => null, 'audit' => SecurityEventCode::COMPETITION_LIVE_SESSION_RESUMED],
            'COMPETITION_LIVE_SESSION_START_RECOVERY' => ['code' => $operation, 'target' => 'RECOVERING', 'step_up' => null, 'audit' => SecurityEventCode::COMPETITION_LIVE_SESSION_RECOVERY_STARTED],
            'COMPETITION_LIVE_SESSION_COMPLETE_RECOVERY' => ['code' => $operation, 'target' => 'OPEN', 'step_up' => null, 'audit' => SecurityEventCode::COMPETITION_LIVE_SESSION_RECOVERED],
            'COMPETITION_LIVE_SESSION_CLOSE' => ['code' => $operation, 'target' => 'CLOSED', 'step_up' => StepUpAction::COMPETITION_LIVE_SESSION_CLOSE, 'audit' => SecurityEventCode::COMPETITION_LIVE_SESSION_CLOSED],
            'COMPETITION_LIVE_SESSION_CANCEL' => ['code' => $operation, 'target' => 'CANCELLED', 'step_up' => StepUpAction::COMPETITION_LIVE_SESSION_CANCEL, 'audit' => SecurityEventCode::COMPETITION_LIVE_SESSION_CANCELLED],
            default => throw new \InvalidArgumentException('Live session operation is invalid.'),
        };
    }

    private function eventType(string $from, string $target): string
    {
        return match ([$from, $target]) {
            ['PLANNED', 'OPEN'] => 'SESSION_OPENED',
            ['PAUSED', 'OPEN'] => 'SESSION_RESUMED',
            ['RECOVERING', 'OPEN'] => 'SESSION_RECOVERED',
            ['OPEN', 'PAUSED'] => 'SESSION_PAUSED',
            ['OPEN', 'RECOVERING'], ['PAUSED', 'RECOVERING'] => 'SESSION_RECOVERY_STARTED',
            ['OPEN', 'CLOSED'], ['PAUSED', 'CLOSED'], ['RECOVERING', 'CLOSED'] => 'SESSION_CLOSED',
            ['PLANNED', 'CANCELLED'], ['OPEN', 'CANCELLED'], ['PAUSED', 'CANCELLED'], ['RECOVERING', 'CANCELLED'] => 'SESSION_CANCELLED',
            default => throw new \DomainException('Live session event transition is invalid.'),
        };
    }
}
