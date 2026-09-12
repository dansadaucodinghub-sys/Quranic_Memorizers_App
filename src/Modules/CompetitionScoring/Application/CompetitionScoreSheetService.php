<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionScoring\Application;

use Qmdb\Modules\CompetitionScoring\Domain\ScoreCriterion;
use Qmdb\Modules\CompetitionScoring\Domain\ScoreSheetCalculator;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
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
 * Authoritative judge draft boundary.  Raw integer units are validated and
 * weighted server-side; browser totals and criterion weights never cross this
 * boundary.
 */
final readonly class CompetitionScoreSheetService
{
    public function __construct(
        private CompetitionScoreSheetRepository $repository,
        private ScoreSheetCalculator $calculator,
        private AuthorizationRequirementGuard $authorization,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    /** @return list<array{code:string,minimum_units:int,maximum_units:int,step_units:int}> */
    public function formCriteria(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $assignmentPublicId): array
    {
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode('workspace.competitions.judge_scores'), new WorkspaceAuthorizationScope($tenant)));
        $assignment = $this->repository->lockAcceptedAssignment($tenant->workspaceInternalId, $actor->accountInternalId, $assignmentPublicId);
        if ($assignment === null || $assignment['round_status'] !== 'SCORING_OPEN') {
            throw new \DomainException('Scoring assignment is unavailable.');
        }
        $rubric = $this->repository->activeRubricForRound($tenant->workspaceInternalId, $assignment['round_id']);
        if ($rubric === null) {
            throw new \DomainException('The round has no active scoring rubric.');
        }
        $result = [];
        foreach ($this->repository->criteria($tenant->workspaceInternalId, $rubric['id']) as $criterion) {
            $result[] = ['code' => $criterion['criterion_code'], 'minimum_units' => $criterion['min_units'], 'maximum_units' => $criterion['max_units'], 'step_units' => $criterion['step_units']];
        }
        if ($result === []) {
            throw new \DomainException('The active rubric has no criteria.');
        }

        return $result;
    }

    /**
     * @param array<string,int> $enteredUnits
     * @return array{public_id:string,version:int,status:string}
     */
    public function saveDraft(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $assignmentPublicId, UuidV7 $participantPublicId, ?int $expectedVersion, array $enteredUnits): array
    {
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode('workspace.competitions.judge_scores'), new WorkspaceAuthorizationScope($tenant)));
        $attempt = new IdentityRateLimitAttempt(IdentityRateLimitScope::COMPETITION_JUDGE_SCORE_DRAFT_ACCOUNT, $this->fingerprints->generate('competition-score-draft', (string) $actor->accountInternalId), new IdentityRateLimitPolicy(60, 30, 60));
        if (!$this->rateLimits->consume([$attempt], $this->clock->now())->allowed) {
            throw new \DomainException('Score draft saving is temporarily unavailable.');
        }

        return $this->transactions->transactional(function () use ($actor, $tenant, $assignmentPublicId, $participantPublicId, $expectedVersion, $enteredUnits): array {
            $assignment = $this->repository->lockAcceptedAssignment($tenant->workspaceInternalId, $actor->accountInternalId, $assignmentPublicId);
            if ($assignment === null || $assignment['round_status'] !== 'SCORING_OPEN') {
                throw new \DomainException('Scoring assignment is unavailable.');
            }
            $rubric = $this->repository->activeRubricForRound($tenant->workspaceInternalId, $assignment['round_id']);
            if ($rubric === null) {
                throw new \DomainException('The round has no active scoring rubric.');
            }
            $criteria = [];
            foreach ($this->repository->criteria($tenant->workspaceInternalId, $rubric['id']) as $criterion) {
                $criteria[] = new ScoreCriterion($criterion['criterion_code'], $criterion['min_units'], $criterion['max_units'], $criterion['step_units'], $criterion['weight_basis_points']);
            }
            if ($criteria === []) {
                throw new \DomainException('The active rubric has no criteria.');
            }
            $calculated = $this->calculator->calculate($criteria, $enteredUnits);
            $existing = $this->repository->lockDraft($tenant->workspaceInternalId, $assignment['id'], $participantPublicId);
            if ($existing !== null && ($expectedVersion === null || $existing['version'] !== $expectedVersion)) {
                throw new \DomainException('Score-sheet draft is stale.');
            }
            if ($existing === null && $expectedVersion !== null) {
                throw new \DomainException('Score-sheet draft is unavailable.');
            }

            return $this->repository->saveDraft($tenant->workspaceInternalId, $assignment['round_id'], $assignment['id'], $rubric['id'], $participantPublicId, $existing, $enteredUnits, $calculated->weightedEntries, $calculated->totalUnits, $calculated->penaltyUnits, $this->clock->now());
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @return array{public_id:string,version:int,status:string} */
    public function prepareForLock(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $scoreSheetPublicId): array
    {
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode('workspace.competitions.judge_scores'), new WorkspaceAuthorizationScope($tenant)));

        return $this->transactions->transactional(fn (): array => $this->repository->prepareForLock($tenant->workspaceInternalId, $actor->accountInternalId, $scoreSheetPublicId, $this->clock->now()), TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }
}
