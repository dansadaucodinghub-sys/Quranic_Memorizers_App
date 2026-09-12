<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Application;

use Qmdb\Modules\CompetitionResults\Domain\CompetitionResultCalculator;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
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

final readonly class CompetitionResultCalculationService
{
    public function __construct(
        private CompetitionResultCalculationRepository $repository,
        private CompetitionResultCalculator $calculator,
        private AuthorizationRequirementGuard $authorization,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    /** @return array{public_id:string,status:string,replayed:bool} */
    public function calculate(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $roundPublicId, int $expectedRoundVersion, bool $denseRanking = false, ?string $correlationId = null): array
    {
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode('workspace.competitions.calculate_results'), new WorkspaceAuthorizationScope($tenant)));
        $attempt = new IdentityRateLimitAttempt(IdentityRateLimitScope::COMPETITION_RESULT_MUTATION_ACCOUNT, $this->fingerprints->generate('competition-result-calculate', (string) $actor->accountInternalId), new IdentityRateLimitPolicy(60, 10, 60));
        if (!$this->rateLimits->consume([$attempt], $this->clock->now())->allowed) {
            throw new \DomainException('Competition result calculation is temporarily unavailable.');
        }

        return $this->transactions->transactional(function () use ($actor, $tenant, $roundPublicId, $expectedRoundVersion, $denseRanking, $correlationId): array {
            $round = $this->repository->lockRound($tenant->workspaceInternalId, $roundPublicId);
            if ($round === null || $round['version'] !== $expectedRoundVersion || $round['status'] !== 'SCORING_CLOSED') {
                throw new \DomainException('A result can be calculated only after scoring closes.');
            }
            $input = $this->repository->lockedCalculationInput($tenant->workspaceInternalId, $round['id']);
            $calculated = $this->calculator->calculate($input['aggregation_method'], $input['locked_scores'], $input['tie_break_rules'], $denseRanking);
            $existing = $this->repository->resultForInput($tenant->workspaceInternalId, $round['id'], $calculated->inputChecksum);
            if ($existing !== null) {
                return ['public_id' => $existing['public_id'], 'status' => $existing['status'], 'replayed' => true];
            }
            $created = $this->repository->persistCalculatedResult($tenant->workspaceInternalId, $round['id'], $input['rubric_id'], $actor->accountInternalId, $calculated, $this->clock->now());
            $this->audit->workspace(SecurityEventCode::COMPETITION_RESULT_CALCULATED, $tenant->workspacePublicId(), SecurityEventSubjectKind::COMPETITION_RESULT_RUN, $created['public_id'], $actor->accountId->toString(), $this->clock->now(), ['round_public_id' => $round['public_id'], 'input_checksum' => $calculated->inputChecksum, 'result_checksum' => $calculated->resultChecksum, 'row_count' => count($calculated->rows)], null, $correlationId);

            return ['public_id' => $created['public_id'], 'status' => $created['status'], 'replayed' => false];
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }
}
