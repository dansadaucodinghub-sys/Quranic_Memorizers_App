<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Application;

use Qmdb\Modules\CompetitionLive\Domain\LiveParticipantLifecycle;
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

/** Authoritative, serialized participant operations for an OPEN live session. */
final readonly class CompetitionLiveParticipantWorkflowService
{
    public function __construct(
        private CompetitionLiveRuntimeRepository $repository,
        private LiveParticipantLifecycle $lifecycle,
        private AuthorizationRequirementGuard $authorization,
        private StepUpGuard $stepUp,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    /** @return array{state:string,version:int,sequence:int,replayed:bool} */
    public function operate(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $submissionId, string $operation, UuidV7 $sessionPublicId, UuidV7 $participantPublicId, int $expectedVersion, ?string $correlationId = null): array
    {
        if ($expectedVersion < 1) {
            throw new \InvalidArgumentException('Live participant version is invalid.');
        }
        $rule = $this->rule($operation);
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode($rule['permission']), new WorkspaceAuthorizationScope($tenant)));
        $this->rate($actor);

        return $this->transactions->transactional(
            fn (): array => $this->inTransaction($actor, $tenant, $submissionId, $rule, $sessionPublicId, $participantPublicId, $expectedVersion, $correlationId),
            TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)),
        );
    }

    /**
     * @param array{code:string,target:string,event:string,permission:string,step_up:?StepUpAction,audit:SecurityEventCode} $rule
     * @return array{state:string,version:int,sequence:int,replayed:bool}
     */
    private function inTransaction(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $submissionId, array $rule, UuidV7 $sessionPublicId, UuidV7 $participantPublicId, int $expectedVersion, ?string $correlationId): array
    {
        $fingerprint = $this->fingerprints->generate('competition-p7-live-participant', implode("\0", [$tenant->workspaceInternalId, $actor->accountInternalId, $rule['code'], $sessionPublicId->toString(), $participantPublicId->toString(), $expectedVersion]));
        $completed = $this->repository->completed($submissionId, $fingerprint->toBinary());
        if ($completed !== null) {
            return ['state' => $completed['status'], 'version' => $completed['version'], 'sequence' => $completed['sequence'], 'replayed' => true];
        }
        $session = $this->repository->lockSession($tenant->workspaceInternalId, $sessionPublicId);
        if ($session === null || $session['status'] !== 'OPEN') {
            throw new \DomainException('Live participant operations require an open session.');
        }
        $participant = $this->repository->lockParticipant($tenant->workspaceInternalId, $participantPublicId);
        if ($participant === null || $participant['live_session_id'] !== $session['id'] || $participant['version'] !== $expectedVersion) {
            throw new \DomainException('Live participant is stale or unavailable.');
        }
        $this->lifecycle->assertTransition($participant['current_state'], $rule['target']);
        if ($rule['step_up'] !== null) {
            $this->stepUp->consumeWithGrant($actor, $rule['step_up']);
        }
        $now = $this->clock->now();
        if (!$this->repository->transitionParticipant($participant, $rule['target'], $now)) {
            throw new \DomainException('Live participant changed concurrently.');
        }
        $eventCorrelationId = $correlationId === null ? UuidV7::generate() : UuidV7::fromString($correlationId);
        $sequence = $this->repository->appendEvent($session, $rule['event'], 'WORKSPACE_PRIVATE', ['session_public_id' => $session['public_id'], 'participant_public_id' => $participant['public_id'], 'operation' => $rule['code'], 'previous_state' => $participant['current_state'], 'state' => $rule['target']], $actor->accountInternalId, $eventCorrelationId, $now);
        $this->audit->workspace($rule['audit'], $tenant->workspacePublicId(), SecurityEventSubjectKind::COMPETITION_LIVE_PARTICIPANT, $participant['public_id'], $actor->accountId->toString(), $now, ['operation' => $rule['code'], 'session_public_id' => $session['public_id'], 'previous_state' => $participant['current_state'], 'new_state' => $rule['target'], 'version_before' => $participant['version'], 'version_after' => $participant['version'] + 1, 'sequence' => $sequence], null, $eventCorrelationId->toString());
        $this->repository->recordParticipant($submissionId, $fingerprint->toBinary(), $rule['code'], $participant, $rule['target'], $participant['version'] + 1, $sequence, $now);

        return ['state' => $rule['target'], 'version' => $participant['version'] + 1, 'sequence' => $sequence, 'replayed' => false];
    }

    private function rate(AuthenticatedAccountContext $actor): void
    {
        $attempt = new IdentityRateLimitAttempt(IdentityRateLimitScope::COMPETITION_LIVE_OPERATION_ACCOUNT, $this->fingerprints->generate('competition-p7-live-account', (string) $actor->accountInternalId), new IdentityRateLimitPolicy(60, 30, 60));
        if (!$this->rateLimits->consume([$attempt], $this->clock->now())->allowed) {
            throw new \DomainException('Live operation is temporarily unavailable.');
        }
    }

    /** @return array{code:string,target:string,event:string,permission:string,step_up:?StepUpAction,audit:SecurityEventCode} */
    private function rule(string $operation): array
    {
        return match ($operation) {
            'COMPETITION_LIVE_PARTICIPANT_CHECK_IN' => $this->entry($operation, 'CHECKED_IN', 'PARTICIPANT_CHECKED_IN', 'workspace.competitions.operate_live', null, SecurityEventCode::COMPETITION_LIVE_PARTICIPANT_CHECKED_IN),
            'COMPETITION_LIVE_PARTICIPANT_CALL' => $this->entry($operation, 'CALLED', 'PARTICIPANT_CALLED', 'workspace.competitions.operate_live', null, SecurityEventCode::COMPETITION_LIVE_PARTICIPANT_CALLED),
            'COMPETITION_LIVE_PARTICIPANT_READY' => $this->entry($operation, 'READY', 'PARTICIPANT_READY', 'workspace.competitions.operate_live', null, SecurityEventCode::COMPETITION_LIVE_PARTICIPANT_READY),
            'COMPETITION_LIVE_PARTICIPANT_START' => $this->entry($operation, 'PERFORMING', 'PERFORMANCE_STARTED', 'workspace.competitions.operate_live', null, SecurityEventCode::COMPETITION_LIVE_PARTICIPANT_PERFORMANCE_STARTED),
            'COMPETITION_LIVE_PARTICIPANT_INTERRUPT' => $this->entry($operation, 'INTERRUPTED', 'PERFORMANCE_INTERRUPTED', 'workspace.competitions.operate_live', null, SecurityEventCode::COMPETITION_LIVE_PARTICIPANT_PERFORMANCE_INTERRUPTED),
            'COMPETITION_LIVE_PARTICIPANT_RESUME' => $this->entry($operation, 'PERFORMING', 'PERFORMANCE_RESUMED', 'workspace.competitions.operate_live', null, SecurityEventCode::COMPETITION_LIVE_PARTICIPANT_PERFORMANCE_RESUMED),
            'COMPETITION_LIVE_PARTICIPANT_COMPLETE' => $this->entry($operation, 'COMPLETED', 'PERFORMANCE_COMPLETED', 'workspace.competitions.operate_live', null, SecurityEventCode::COMPETITION_LIVE_PARTICIPANT_PERFORMANCE_COMPLETED),
            'COMPETITION_LIVE_PARTICIPANT_ABSENT' => $this->entry($operation, 'ABSENT', 'PARTICIPANT_ABSENT', 'workspace.competitions.operate_live', null, SecurityEventCode::COMPETITION_LIVE_PARTICIPANT_ABSENT),
            'COMPETITION_LIVE_PARTICIPANT_WITHDRAW' => $this->entry($operation, 'WITHDRAWN', 'PARTICIPANT_WITHDRAWN', 'workspace.competitions.operate_live', null, SecurityEventCode::COMPETITION_LIVE_PARTICIPANT_WITHDRAWN),
            'COMPETITION_LIVE_PARTICIPANT_DISQUALIFY' => $this->entry($operation, 'DISQUALIFIED', 'PARTICIPANT_DISQUALIFIED', 'workspace.competitions.disqualify_participants', StepUpAction::COMPETITION_PARTICIPANT_DISQUALIFY, SecurityEventCode::COMPETITION_PARTICIPANT_DISQUALIFIED),
            default => throw new \InvalidArgumentException('Live participant operation is invalid.'),
        };
    }

    /** @return array{code:string,target:string,event:string,permission:string,step_up:?StepUpAction,audit:SecurityEventCode} */
    private function entry(string $code, string $target, string $event, string $permission, ?StepUpAction $stepUp, SecurityEventCode $audit): array
    {
        return ['code' => $code, 'target' => $target, 'event' => $event, 'permission' => $permission, 'step_up' => $stepUp, 'audit' => $audit];
    }
}
