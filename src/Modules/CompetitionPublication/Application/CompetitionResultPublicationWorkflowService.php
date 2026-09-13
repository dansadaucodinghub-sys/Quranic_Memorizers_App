<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionPublication\Application;

use Qmdb\Modules\CompetitionPublication\Domain\ResultPublicationLifecycle;
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

/** Closed P7 publication gateway: no browser-selected SQL or status strings. */
final readonly class CompetitionResultPublicationWorkflowService
{
    public function __construct(
        private CompetitionResultPublicationRepository $repository,
        private ResultPublicationLifecycle $lifecycle,
        private AuthorizationRequirementGuard $authorization,
        private StepUpGuard $stepUp,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    /** @return array{publication_id:string,status:string,version:int,replayed:bool} */
    public function prepare(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $submissionId, UuidV7 $resultRunId, ?string $correlationId = null): array
    {
        $rule = $this->rule('COMPETITION_RESULT_PUBLICATION_PREPARE');
        $this->allow($actor, $tenant, $rule['permission']);
        $this->rate($actor);

        return $this->transactions->transactional(function () use ($actor, $tenant, $submissionId, $resultRunId, $rule, $correlationId): array {
            $fingerprint = $this->fingerprint($tenant, $actor, $rule['code'], $resultRunId->toString(), 1);
            $completed = $this->repository->completed($submissionId, $fingerprint);
            if ($completed !== null) {
                return ['publication_id' => $completed['publication_id'], 'status' => $completed['status'], 'version' => $completed['version'], 'replayed' => true];
            }
            $resultRun = $this->repository->lockPublishedResultRun($tenant->workspaceInternalId, $resultRunId);
            if ($resultRun === null) {
                throw new \DomainException('Published result run is unavailable for publication preparation.');
            }
            $publication = $this->repository->prepare($resultRun, $actor->accountInternalId, $this->clock->now());
            $this->repository->appendEvent($publication, 'PREPARED', $actor->accountInternalId, $this->clock->now());
            $this->repository->record($submissionId, $fingerprint, $rule['code'], $publication, 'PREPARED', 1, $this->clock->now());
            $this->audit($rule['audit'], $tenant, $publication['public_id'], $actor, 'PREPARED', 1, $correlationId);

            return ['publication_id' => $publication['public_id'], 'status' => 'PREPARED', 'version' => 1, 'replayed' => false];
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @return array{publication_id:string,status:string,version:int,replayed:bool} */
    public function transition(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $submissionId, string $operation, UuidV7 $publicationId, int $expectedVersion, ?string $correlationId = null): array
    {
        $rule = $this->rule($operation);
        $this->allow($actor, $tenant, $rule['permission']);
        $this->rate($actor);

        return $this->transactions->transactional(function () use ($actor, $tenant, $submissionId, $rule, $publicationId, $expectedVersion, $correlationId): array {
            $fingerprint = $this->fingerprint($tenant, $actor, $rule['code'], $publicationId->toString(), $expectedVersion);
            $completed = $this->repository->completed($submissionId, $fingerprint);
            if ($completed !== null) {
                return ['publication_id' => $completed['publication_id'], 'status' => $completed['status'], 'version' => $completed['version'], 'replayed' => true];
            }
            $publication = $this->repository->lockPublication($tenant->workspaceInternalId, $publicationId);
            if ($publication === null || $publication['version'] !== $expectedVersion) {
                throw new \DomainException('Result publication is stale or unavailable.');
            }
            $this->lifecycle->assertTransition($publication['status'], $rule['target']);
            if ($rule['step_up'] !== null) {
                $this->stepUp->consumeWithGrant($actor, $rule['step_up']);
            }
            $now = $this->clock->now();
            if (!$this->repository->transition($publication, $rule['target'], $actor->accountInternalId, $now)) {
                throw new \DomainException('Result publication changed concurrently.');
            }
            $this->repository->appendEvent($publication, $rule['event'], $actor->accountInternalId, $now);
            $this->repository->record($submissionId, $fingerprint, $rule['code'], $publication, $rule['target'], $publication['version'] + 1, $now);
            $this->audit($rule['audit'], $tenant, $publication['public_id'], $actor, $rule['target'], $publication['version'] + 1, $correlationId);

            return ['publication_id' => $publication['public_id'], 'status' => $rule['target'], 'version' => $publication['version'] + 1, 'replayed' => false];
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    private function allow(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, string $permission): void
    {
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode($permission), new WorkspaceAuthorizationScope($tenant)));
    }

    private function rate(AuthenticatedAccountContext $actor): void
    {
        $attempt = new IdentityRateLimitAttempt(IdentityRateLimitScope::COMPETITION_RESULT_MUTATION_ACCOUNT, $this->fingerprints->generate('competition-p7-publication-account', (string) $actor->accountInternalId), new IdentityRateLimitPolicy(60, 20, 60));
        if (!$this->rateLimits->consume([$attempt], $this->clock->now())->allowed) {
            throw new \DomainException('Result publication operation is temporarily unavailable.');
        }
    }

    private function fingerprint(AccountWorkspaceTenantContext $tenant, AuthenticatedAccountContext $actor, string $operation, string $aggregateId, int $version): string
    {
        return $this->fingerprints->generate('competition-p7-publication-operation', implode("\0", [$tenant->workspaceInternalId, $actor->accountInternalId, $operation, $aggregateId, $version]))->toBinary();
    }

    private function audit(SecurityEventCode $code, AccountWorkspaceTenantContext $tenant, string $publicationId, AuthenticatedAccountContext $actor, string $status, int $version, ?string $correlationId): void
    {
        $this->audit->workspace($code, $tenant->workspacePublicId(), SecurityEventSubjectKind::COMPETITION_RESULT_PUBLICATION, $publicationId, $actor->accountId->toString(), $this->clock->now(), ['status' => $status, 'version' => $version], null, $correlationId);
    }

    /** @return array{code:string,target:string,event:string,permission:string,step_up:?StepUpAction,audit:SecurityEventCode} */
    private function rule(string $operation): array
    {
        return match ($operation) {
            'COMPETITION_RESULT_PUBLICATION_PREPARE' => ['code' => $operation, 'target' => 'PREPARED', 'event' => 'PREPARED', 'permission' => 'workspace.competitions.prepare_publications', 'step_up' => null, 'audit' => SecurityEventCode::COMPETITION_RESULT_PUBLICATION_PREPARED],
            'COMPETITION_RESULT_PUBLICATION_PUBLISH_PROVISIONAL' => ['code' => $operation, 'target' => 'PROVISIONAL_PUBLISHED', 'event' => 'PROVISIONAL_PUBLISHED', 'permission' => 'workspace.competitions.publish_provisional_results', 'step_up' => StepUpAction::COMPETITION_RESULT_PUBLISH, 'audit' => SecurityEventCode::COMPETITION_RESULT_PUBLICATION_PROVISIONALLY_PUBLISHED],
            'COMPETITION_RESULT_PUBLICATION_HOLD' => ['code' => $operation, 'target' => 'HELD', 'event' => 'HELD', 'permission' => 'workspace.competitions.hold_publications', 'step_up' => StepUpAction::COMPETITION_RESULT_VOID, 'audit' => SecurityEventCode::COMPETITION_RESULT_PUBLICATION_HELD],
            'COMPETITION_RESULT_PUBLICATION_RELEASE_HOLD' => ['code' => $operation, 'target' => 'PROVISIONAL_PUBLISHED', 'event' => 'HOLD_RELEASED', 'permission' => 'workspace.competitions.hold_publications', 'step_up' => StepUpAction::COMPETITION_RESULT_PUBLISH, 'audit' => SecurityEventCode::COMPETITION_RESULT_PUBLICATION_PROVISIONALLY_PUBLISHED],
            'COMPETITION_RESULT_PUBLICATION_FINALIZE' => ['code' => $operation, 'target' => 'FINALIZED', 'event' => 'FINALIZED', 'permission' => 'workspace.competitions.finalize_publications', 'step_up' => StepUpAction::COMPETITION_RESULT_PUBLISH, 'audit' => SecurityEventCode::COMPETITION_RESULT_PUBLICATION_FINALIZED],
            'COMPETITION_RESULT_PUBLICATION_WITHDRAW' => ['code' => $operation, 'target' => 'WITHDRAWN', 'event' => 'WITHDRAWN', 'permission' => 'workspace.competitions.hold_publications', 'step_up' => StepUpAction::COMPETITION_RESULT_VOID, 'audit' => SecurityEventCode::COMPETITION_RESULT_PUBLICATION_WITHDRAWN],
            'COMPETITION_RESULT_PUBLICATION_SUPERSEDE' => ['code' => $operation, 'target' => 'SUPERSEDED', 'event' => 'SUPERSEDED', 'permission' => 'workspace.competitions.finalize_publications', 'step_up' => StepUpAction::COMPETITION_RESULT_PUBLISH, 'audit' => SecurityEventCode::COMPETITION_RESULT_PUBLICATION_WITHDRAWN],
            'COMPETITION_RESULT_PUBLICATION_ARCHIVE' => ['code' => $operation, 'target' => 'ARCHIVED', 'event' => 'ARCHIVED', 'permission' => 'workspace.competitions.hold_publications', 'step_up' => StepUpAction::COMPETITION_RESULT_VOID, 'audit' => SecurityEventCode::COMPETITION_RESULT_PUBLICATION_WITHDRAWN],
            default => throw new \InvalidArgumentException('Result publication operation is invalid.'),
        };
    }
}
