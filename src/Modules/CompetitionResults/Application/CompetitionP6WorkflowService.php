<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Application;

use Qmdb\Modules\CompetitionJudging\Domain\RoundLifecycle;
use Qmdb\Modules\CompetitionJudging\Domain\RoundStatus;
use Qmdb\Modules\CompetitionScoring\Domain\ScoreSheetLifecycle;
use Qmdb\Modules\CompetitionScoring\Domain\ScoreSheetStatus;
use Qmdb\Modules\CompetitionResults\Domain\AppealLifecycle;
use Qmdb\Modules\CompetitionResults\Domain\AppealStatus;
use Qmdb\Modules\CompetitionResults\Domain\ResultRunLifecycle;
use Qmdb\Modules\CompetitionResults\Domain\ResultRunStatus;
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
 * One authoritative P6 mutation gateway.  It is deliberately closed over the
 * supported lifecycle operations so browser input can never select SQL, a
 * target status, or an authorization rule.
 */
final readonly class CompetitionP6WorkflowService
{
    public function __construct(
        private CompetitionP6RuntimeRepository $repository,
        private AuthorizationRequirementGuard $authorization,
        private StepUpGuard $stepUp,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private Clock $clock,
        private RoundLifecycle $rounds,
        private ScoreSheetLifecycle $scoreSheets,
        private ResultRunLifecycle $resultRuns,
        private AppealLifecycle $appeals,
    ) {
    }

    /** @return array{status:string,version:int,replayed:bool} */
    public function transition(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submissionId,
        string $operation,
        UuidV7 $aggregatePublicId,
        int $expectedVersion,
        ?string $correlationId = null,
    ): array {
        $rule = $this->rule($operation);
        $this->authorization->requireAllowed(new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($actor),
            new PermissionCode($rule['permission']),
            new WorkspaceAuthorizationScope($tenant),
        ));
        $this->rate($actor, $rule['rate']);

        return $this->transactions->transactional(
            fn (): array => $this->inTransaction($actor, $tenant, $submissionId, $rule, $aggregatePublicId, $expectedVersion, $correlationId),
            TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)),
        );
    }

    /** @param array{code:string,kind:string,target:string,permission:string,step_up:?StepUpAction,rate:IdentityRateLimitScope,audit:?SecurityEventCode,subject:SecurityEventSubjectKind,notify:bool} $rule @return array{status:string,version:int,replayed:bool} */
    private function inTransaction(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $submissionId, array $rule, UuidV7 $aggregatePublicId, int $expectedVersion, ?string $correlationId): array
    {
        $fingerprint = $this->fingerprints->generate('competition-p6-operation', implode("\0", [$tenant->workspaceInternalId, $actor->accountInternalId, $rule['code'], $aggregatePublicId->toString(), $expectedVersion]));
        $completed = $this->repository->completed($submissionId, $fingerprint->toBinary());
        if ($completed !== null) {
            return ['status' => $completed['status'], 'version' => $completed['version'], 'replayed' => true];
        }
        $aggregate = $this->repository->lock($rule['kind'], $tenant->workspaceInternalId, $aggregatePublicId);
        if ($aggregate === null || $aggregate['version'] !== $expectedVersion) {
            throw new \DomainException('Competition record is stale or unavailable.');
        }
        if ($rule['kind'] === 'ASSIGNMENT' && in_array($rule['code'], ['COMPETITION_ASSIGNMENT_ACCEPT', 'COMPETITION_ASSIGNMENT_DECLINE'], true) && ($aggregate['judge_account_id'] ?? 0) !== $actor->accountInternalId) {
            throw new \DomainException('Judge assignment is unavailable.');
        }
        $this->assertLifecycle($rule['kind'], $aggregate['status'], $rule['target']);
        if ($rule['step_up'] !== null) {
            $this->stepUp->consumeWithGrant($actor, $rule['step_up']);
        }
        $now = $this->clock->now();
        if (!$this->repository->transition($rule['kind'], $aggregate, $rule['target'], $actor->accountInternalId, $now)) {
            throw new \DomainException('Competition record is stale.');
        }
        $metadata = ['operation' => $rule['code'], 'previous_status' => $aggregate['status'], 'new_status' => $rule['target'], 'version_before' => $aggregate['version'], 'version_after' => $aggregate['version'] + 1];
        $this->repository->appendEvent($rule['kind'], $aggregate, $rule['code'], $actor->accountInternalId, $metadata, $now);
        if ($rule['audit'] !== null) {
            $this->audit->workspace($rule['audit'], $tenant->workspacePublicId(), $rule['subject'], $aggregate['public_id'], $actor->accountId->toString(), $now, $metadata, null, $correlationId);
        }
        if ($rule['notify']) {
            $this->repository->notificationIntent($aggregate, $rule['kind'] === 'ASSIGNMENT' ? ($aggregate['judge_account_id'] ?? null) : null, $rule['code'], ['aggregate_public_id' => $aggregate['public_id'], 'new_status' => $rule['target']], $now);
        }
        $this->repository->record($submissionId, $fingerprint->toBinary(), $rule['code'], $aggregate, $rule['target'], $aggregate['version'] + 1, $now);

        return ['status' => $rule['target'], 'version' => $aggregate['version'] + 1, 'replayed' => false];
    }

    private function assertLifecycle(string $kind, string $from, string $target): void
    {
        match ($kind) {
            'ROUND' => $this->rounds->assertTransition(RoundStatus::from($from), RoundStatus::from($target)),
            'SCORE_SHEET' => $this->scoreSheets->assertTransition(ScoreSheetStatus::from($from), ScoreSheetStatus::from($target)),
            'RESULT_RUN' => $this->resultRuns->assertTransition(ResultRunStatus::from($from), ResultRunStatus::from($target)),
            'APPEAL' => $this->appeals->assertTransition(AppealStatus::from($from), AppealStatus::from($target)),
            'ASSIGNMENT' => $this->assertAssignmentTransition($from, $target),
            default => throw new \InvalidArgumentException('P6 aggregate kind is invalid.'),
        };
    }

    private function assertAssignmentTransition(string $from, string $target): void
    {
        $allowed = ['ASSIGNED' => ['ACCEPTED', 'DECLINED', 'REVOKED'], 'ACCEPTED' => ['REVOKED', 'COMPLETED']];
        if (!in_array($target, $allowed[$from] ?? [], true)) {
            throw new \DomainException('Judge assignment transition is unavailable.');
        }
    }

    private function rate(AuthenticatedAccountContext $actor, IdentityRateLimitScope $scope): void
    {
        $policy = new IdentityRateLimitPolicy(60, 20, 60);
        $account = new IdentityRateLimitAttempt($scope, $this->fingerprints->generate('competition-p6-account', (string) $actor->accountInternalId), $policy);
        if (!$this->rateLimits->consume([$account], $this->clock->now())->allowed) {
            throw new \DomainException('Competition operation is temporarily unavailable.');
        }
    }

    /** @return array{code:string,kind:string,target:string,permission:string,step_up:?StepUpAction,rate:IdentityRateLimitScope,audit:?SecurityEventCode,subject:SecurityEventSubjectKind,notify:bool} */
    private function rule(string $operation): array
    {
        return match ($operation) {
            'COMPETITION_ROUND_READY' => $this->entry($operation, 'ROUND', 'READY', 'workspace.competitions.manage_rounds', null, IdentityRateLimitScope::COMPETITION_RESULT_MUTATION_ACCOUNT, null, SecurityEventSubjectKind::COMPETITION_ROUND),
            'COMPETITION_ROUND_OPEN_SCORING' => $this->entry($operation, 'ROUND', 'SCORING_OPEN', 'workspace.competitions.manage_rounds', StepUpAction::COMPETITION_SCORING_OPEN, IdentityRateLimitScope::COMPETITION_RESULT_MUTATION_ACCOUNT, null, SecurityEventSubjectKind::COMPETITION_ROUND),
            'COMPETITION_ROUND_CLOSE_SCORING' => $this->entry($operation, 'ROUND', 'SCORING_CLOSED', 'workspace.competitions.manage_rounds', StepUpAction::COMPETITION_SCORING_CLOSE, IdentityRateLimitScope::COMPETITION_RESULT_MUTATION_ACCOUNT, null, SecurityEventSubjectKind::COMPETITION_ROUND),
            'COMPETITION_ROUND_CANCEL' => $this->entry($operation, 'ROUND', 'CANCELLED', 'workspace.competitions.manage_rounds', StepUpAction::COMPETITION_RESULT_VOID, IdentityRateLimitScope::COMPETITION_RESULT_MUTATION_ACCOUNT, null, SecurityEventSubjectKind::COMPETITION_ROUND),
            'COMPETITION_ASSIGNMENT_ACCEPT' => $this->entry($operation, 'ASSIGNMENT', 'ACCEPTED', 'workspace.competitions.judge_scores', null, IdentityRateLimitScope::COMPETITION_JUDGE_SCORE_SUBMIT_ACCOUNT, null, SecurityEventSubjectKind::COMPETITION_JUDGE, true),
            'COMPETITION_ASSIGNMENT_DECLINE' => $this->entry($operation, 'ASSIGNMENT', 'DECLINED', 'workspace.competitions.judge_scores', null, IdentityRateLimitScope::COMPETITION_JUDGE_SCORE_SUBMIT_ACCOUNT, null, SecurityEventSubjectKind::COMPETITION_JUDGE, true),
            'COMPETITION_ASSIGNMENT_REVOKE' => $this->entry($operation, 'ASSIGNMENT', 'REVOKED', 'workspace.competitions.manage_panels', StepUpAction::COMPETITION_SCORE_CORRECT, IdentityRateLimitScope::COMPETITION_RESULT_MUTATION_ACCOUNT, null, SecurityEventSubjectKind::COMPETITION_JUDGE, true),
            'COMPETITION_SCORE_SUBMIT' => $this->entry($operation, 'SCORE_SHEET', 'SUBMITTED', 'workspace.competitions.judge_scores', null, IdentityRateLimitScope::COMPETITION_JUDGE_SCORE_SUBMIT_ACCOUNT, null, SecurityEventSubjectKind::COMPETITION_SCORE_SHEET),
            'COMPETITION_SCORE_LOCK' => $this->entry($operation, 'SCORE_SHEET', 'LOCKED', 'workspace.competitions.judge_scores', null, IdentityRateLimitScope::COMPETITION_JUDGE_SCORE_SUBMIT_ACCOUNT, SecurityEventCode::COMPETITION_SCORE_SHEET_LOCKED, SecurityEventSubjectKind::COMPETITION_SCORE_SHEET),
            'COMPETITION_SCORE_RETURN_TO_DRAFT' => $this->entry($operation, 'SCORE_SHEET', 'DRAFT', 'workspace.competitions.correct_scores', StepUpAction::COMPETITION_SCORE_CORRECT, IdentityRateLimitScope::COMPETITION_RESULT_MUTATION_ACCOUNT, null, SecurityEventSubjectKind::COMPETITION_SCORE_SHEET),
            'COMPETITION_SCORE_SUPERSEDE' => $this->entry($operation, 'SCORE_SHEET', 'SUPERSEDED', 'workspace.competitions.correct_scores', StepUpAction::COMPETITION_SCORE_CORRECT, IdentityRateLimitScope::COMPETITION_RESULT_MUTATION_ACCOUNT, SecurityEventCode::COMPETITION_SCORE_SHEET_SUPERSEDED, SecurityEventSubjectKind::COMPETITION_SCORE_SHEET),
            'COMPETITION_SCORE_VOID' => $this->entry($operation, 'SCORE_SHEET', 'VOIDED', 'workspace.competitions.correct_scores', StepUpAction::COMPETITION_SCORE_CORRECT, IdentityRateLimitScope::COMPETITION_RESULT_MUTATION_ACCOUNT, null, SecurityEventSubjectKind::COMPETITION_SCORE_SHEET),
            'COMPETITION_RESULT_VERIFY' => $this->entry($operation, 'RESULT_RUN', 'VERIFIED', 'workspace.competitions.verify_results', StepUpAction::COMPETITION_RESULT_VERIFY, IdentityRateLimitScope::COMPETITION_RESULT_MUTATION_ACCOUNT, SecurityEventCode::COMPETITION_RESULT_VERIFIED, SecurityEventSubjectKind::COMPETITION_RESULT_RUN),
            'COMPETITION_RESULT_PUBLISH' => $this->entry($operation, 'RESULT_RUN', 'PUBLISHED', 'workspace.competitions.publish_results', StepUpAction::COMPETITION_RESULT_PUBLISH, IdentityRateLimitScope::COMPETITION_RESULT_MUTATION_ACCOUNT, SecurityEventCode::COMPETITION_RESULT_PUBLISHED, SecurityEventSubjectKind::COMPETITION_RESULT_RUN, true),
            'COMPETITION_RESULT_VOID' => $this->entry($operation, 'RESULT_RUN', 'VOIDED', 'workspace.competitions.publish_results', StepUpAction::COMPETITION_RESULT_VOID, IdentityRateLimitScope::COMPETITION_RESULT_MUTATION_ACCOUNT, null, SecurityEventSubjectKind::COMPETITION_RESULT_RUN),
            'COMPETITION_APPEAL_START_REVIEW' => $this->entry($operation, 'APPEAL', 'UNDER_REVIEW', 'workspace.competitions.manage_appeals', null, IdentityRateLimitScope::COMPETITION_APPEAL_REVIEW_ACCOUNT, null, SecurityEventSubjectKind::COMPETITION_APPEAL),
            'COMPETITION_APPEAL_UPHOLD' => $this->entry($operation, 'APPEAL', 'UPHELD', 'workspace.competitions.manage_appeals', StepUpAction::COMPETITION_APPEAL_UPHOLD, IdentityRateLimitScope::COMPETITION_APPEAL_REVIEW_ACCOUNT, SecurityEventCode::COMPETITION_APPEAL_DECIDED, SecurityEventSubjectKind::COMPETITION_APPEAL, true),
            'COMPETITION_APPEAL_DISMISS' => $this->entry($operation, 'APPEAL', 'DISMISSED', 'workspace.competitions.manage_appeals', StepUpAction::COMPETITION_APPEAL_DISMISS, IdentityRateLimitScope::COMPETITION_APPEAL_REVIEW_ACCOUNT, SecurityEventCode::COMPETITION_APPEAL_DECIDED, SecurityEventSubjectKind::COMPETITION_APPEAL, true),
            'COMPETITION_APPEAL_WITHDRAW' => $this->entry($operation, 'APPEAL', 'WITHDRAWN', 'workspace.competitions.manage_appeals', null, IdentityRateLimitScope::COMPETITION_APPEAL_SUBMIT_ACCOUNT, null, SecurityEventSubjectKind::COMPETITION_APPEAL),
            default => throw new \InvalidArgumentException('Competition operation is invalid.'),
        };
    }

    /** @return array{code:string,kind:string,target:string,permission:string,step_up:?StepUpAction,rate:IdentityRateLimitScope,audit:?SecurityEventCode,subject:SecurityEventSubjectKind,notify:bool} */
    private function entry(string $code, string $kind, string $target, string $permission, ?StepUpAction $stepUp, IdentityRateLimitScope $rate, ?SecurityEventCode $audit, SecurityEventSubjectKind $subject, bool $notify = false): array
    {
        return ['code' => $code, 'kind' => $kind, 'target' => $target, 'permission' => $permission, 'step_up' => $stepUp, 'rate' => $rate, 'audit' => $audit, 'subject' => $subject, 'notify' => $notify];
    }
}
