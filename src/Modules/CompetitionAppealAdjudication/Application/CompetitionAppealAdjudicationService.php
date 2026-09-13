<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionAppealAdjudication\Application;

use DateTimeImmutable;
use Qmdb\Modules\Identity\Infrastructure\Security\ContactCipher;
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
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Schema\State\PdoResultReader;
use Qmdb\Shared\Time\Clock;
use PDO;
use PDOStatement;

/**
 * Closed P7 appeal-adjudication gateway. Decisions require two independent,
 * conflict-free accepted reviewers and are persisted with the P6 appeal state
 * inside the same transaction.
 */
final readonly class CompetitionAppealAdjudicationService
{
    public function __construct(
        private DatabaseConnectionProvider $connections,
        private ContactCipher $cipher,
        private AuthorizationRequirementGuard $authorization,
        private StepUpGuard $stepUp,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    /** @return array{assignment_id:string,status:string,replayed:bool} */
    public function assignSelf(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $submission, UuidV7 $appealId): array
    {
        return $this->transact($actor, $tenant, $submission, 'COMPETITION_APPEAL_ADJUDICATION_ASSIGN_SELF', $appealId, 1, function (array $appeal, DateTimeImmutable $now) use ($actor): array {
            $pdo = $this->connections->connection();
            $exists = $this->statement('SELECT public_id,status FROM competition_appeal_review_assignments WHERE workspace_id=:workspace_id AND appeal_id=:appeal_id AND reviewer_account_id=:account_id FOR UPDATE');
            $exists->execute([':workspace_id' => $appeal['workspace_id'], ':appeal_id' => $appeal['id'], ':account_id' => $actor->accountInternalId]);
            $row = $this->row($exists);
            if (is_array($row)) {
                return ['assignment_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'public_id'))->toString(), 'status' => PdoResultReader::string($row, 'status')];
            }
            $primary = $this->statement("SELECT id FROM competition_appeal_review_assignments WHERE workspace_id=:workspace_id AND appeal_id=:appeal_id AND reviewer_role='PRIMARY_REVIEWER' AND status IN ('ASSIGNED','ACCEPTED') FOR UPDATE");
            $primary->execute([':workspace_id' => $appeal['workspace_id'], ':appeal_id' => $appeal['id']]);
            $role = $primary->fetchColumn() === false ? 'PRIMARY_REVIEWER' : 'SECONDARY_REVIEWER';
            $publicId = UuidV7::generate();
            $insert = $this->statement('INSERT INTO competition_appeal_review_assignments (public_id,workspace_id,appeal_id,reviewer_account_id,reviewer_role,status,version,assigned_by_account_id,assigned_at,created_at,updated_at) VALUES (:public_id,:workspace_id,:appeal_id,:reviewer_account_id,:reviewer_role,\'ASSIGNED\',1,:assigned_by,:assigned_at,:created_at,:updated_at)');
            $time = $this->time($now);
            $insert->execute([':public_id' => $publicId->toBinary(), ':workspace_id' => $appeal['workspace_id'], ':appeal_id' => $appeal['id'], ':reviewer_account_id' => $actor->accountInternalId, ':reviewer_role' => $role, ':assigned_by' => $actor->accountInternalId, ':assigned_at' => $time, ':created_at' => $time, ':updated_at' => $time]);

            return ['assignment_id' => $publicId->toString(), 'status' => 'ASSIGNED'];
        });
    }

    /** @return array{assignment_id:string,status:string,replayed:bool} */
    public function accept(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $submission, UuidV7 $assignmentId, int $expectedVersion): array
    {
        $this->allow($actor, $tenant);
        $this->rate($actor);

        return $this->transactions->transactional(function () use ($actor, $tenant, $submission, $assignmentId, $expectedVersion): array {
            $fingerprint = $this->fingerprint($tenant, $actor, 'COMPETITION_APPEAL_ADJUDICATION_ACCEPT', $assignmentId, $expectedVersion);
            $done = $this->completed($submission, $fingerprint);
            if ($done !== null) {
                return ['assignment_id' => $done['aggregate_id'], 'status' => $done['status'], 'replayed' => true];
            }
            $assignment = $this->lockAssignment($tenant->workspaceInternalId, $assignmentId);
            if ($assignment === null || $assignment['reviewer_account_id'] !== $actor->accountInternalId || $assignment['status'] !== 'ASSIGNED' || $assignment['version'] !== $expectedVersion) {
                throw new \DomainException('Appeal review assignment is stale or unavailable.');
            }
            $now = $this->clock->now();
            $update = $this->statement("UPDATE competition_appeal_review_assignments SET status='ACCEPTED',version=version+1,accepted_at=:accepted_at,updated_at=:updated_at WHERE id=:id AND workspace_id=:workspace_id AND status='ASSIGNED' AND version=:version");
            $update->execute([':accepted_at' => $this->time($now), ':updated_at' => $this->time($now), ':id' => $assignment['id'], ':workspace_id' => $tenant->workspaceInternalId, ':version' => $expectedVersion]);
            if ($update->rowCount() !== 1) {
                throw new \DomainException('Appeal review assignment changed concurrently.');
            }
            $this->record($submission, $fingerprint, 'COMPETITION_APPEAL_ADJUDICATION_ACCEPT', $tenant->workspaceInternalId, $assignmentId, 'ACCEPTED', $expectedVersion + 1, $now);

            return ['assignment_id' => $assignmentId->toString(), 'status' => 'ACCEPTED', 'replayed' => false];
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @return array{appeal_id:string,status:string,replayed:bool} */
    public function decide(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $submission, UuidV7 $appealId, int $expectedVersion, string $decision, string $reasonCode, string $summary): array
    {
        if (!in_array($decision, ['UPHELD', 'PARTIALLY_UPHELD', 'DISMISSED'], true) || preg_match('/\A[A-Z0-9_]{1,64}\z/', $reasonCode) !== 1 || $summary === '' || mb_strlen($summary, 'UTF-8') > 4000) {
            throw new \InvalidArgumentException('Appeal decision input is invalid.');
        }
        $this->allow($actor, $tenant);
        $this->rate($actor);
        $this->stepUp->consumeWithGrant($actor, $decision === 'DISMISSED' ? StepUpAction::COMPETITION_APPEAL_DISMISS : StepUpAction::COMPETITION_APPEAL_UPHOLD);

        return $this->transactions->transactional(function () use ($actor, $tenant, $submission, $appealId, $expectedVersion, $decision, $reasonCode, $summary): array {
            $fingerprint = $this->fingerprint($tenant, $actor, 'COMPETITION_APPEAL_ADJUDICATION_DECIDE_' . $decision, $appealId, $expectedVersion);
            $done = $this->completed($submission, $fingerprint);
            if ($done !== null) {
                return ['appeal_id' => $done['aggregate_id'], 'status' => $done['status'], 'replayed' => true];
            }
            $appeal = $this->lockAppeal($tenant->workspaceInternalId, $appealId);
            if ($appeal === null || $appeal['status'] !== 'UNDER_REVIEW' || $appeal['version'] !== $expectedVersion) {
                throw new \DomainException('Appeal is stale or unavailable for adjudication.');
            }
            $this->requireIndependentReviewers($tenant->workspaceInternalId, $appeal['id'], $actor->accountInternalId);
            $now = $this->clock->now();
            $effects = $decision === 'DISMISSED' ? ['NO_CHANGE'] : ['RESULT_RECALCULATION_REQUIRED', 'PUBLICATION_CORRECTION_REQUIRED'];
            $canonical = \Qmdb\Modules\CompetitionLive\Infrastructure\Persistence\CanonicalJson::encode(['effects' => $effects]);
            $authorizationHash = hash('sha256', $appealId->toBinary() . "\0" . $decision . "\0" . $canonical, true);
            $insert = $this->statement('INSERT INTO competition_appeal_decisions (public_id,workspace_id,appeal_id,decision_type,safe_reason_code,confidential_summary_ciphertext,confidential_summary_key_id,correction_effects_canonical_json,correction_authorization_sha256,decided_by_account_id,decided_at,created_at) VALUES (:public_id,:workspace_id,:appeal_id,:decision_type,:reason_code,:summary,:key_id,:effects,:authorization_hash,:decided_by,:decided_at,:created_at)');
            $time = $this->time($now);
            $insert->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $tenant->workspaceInternalId, ':appeal_id' => $appeal['id'], ':decision_type' => $decision, ':reason_code' => $reasonCode, ':summary' => $this->cipher->encrypt($summary), ':key_id' => $this->cipher->keyId(), ':effects' => $canonical, ':authorization_hash' => $authorizationHash, ':decided_by' => $actor->accountInternalId, ':decided_at' => $time, ':created_at' => $time]);
            $decisionId = (int) $this->connections->connection()->lastInsertId();
            foreach ($effects as $effect) {
                $authorization = $this->statement('INSERT INTO competition_appeal_correction_authorizations (public_id,workspace_id,appeal_decision_id,authorization_sha256,correction_effect,status,created_at) VALUES (:public_id,:workspace_id,:decision_id,:authorization_hash,:effect,\'AUTHORIZED\',:created_at)');
                $authorization->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $tenant->workspaceInternalId, ':decision_id' => $decisionId, ':authorization_hash' => $authorizationHash, ':effect' => $effect, ':created_at' => $time]);
            }
            if (in_array('PUBLICATION_CORRECTION_REQUIRED', $effects, true)) {
                $this->placePublicationHold($appeal, $actor->accountInternalId, $reasonCode, $now);
            }
            $target = $decision === 'DISMISSED' ? 'DISMISSED' : 'UPHELD';
            $update = $this->statement('UPDATE competition_appeals SET status=:status,version=version+1,decided_at=:decided_at WHERE id=:id AND workspace_id=:workspace_id AND status=\'UNDER_REVIEW\' AND version=:version');
            $update->execute([':status' => $target, ':decided_at' => $time, ':id' => $appeal['id'], ':workspace_id' => $tenant->workspaceInternalId, ':version' => $expectedVersion]);
            if ($update->rowCount() !== 1) {
                throw new \DomainException('Appeal changed concurrently.');
            }
            $this->record($submission, $fingerprint, 'COMPETITION_APPEAL_ADJUDICATION_DECIDE_' . $decision, $tenant->workspaceInternalId, $appealId, $target, $expectedVersion + 1, $now);
            $this->audit->workspace(SecurityEventCode::COMPETITION_APPEAL_DECIDED, $tenant->workspacePublicId(), SecurityEventSubjectKind::COMPETITION_APPEAL_ADJUDICATION, $appealId->toString(), $actor->accountId->toString(), $now, ['decision' => $decision, 'reason_code' => $reasonCode], null, null);

            return ['appeal_id' => $appealId->toString(), 'status' => $target, 'replayed' => false];
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /**
     * @param callable(array{id:int,workspace_id:int,result_run_id:int,status:string,version:int},DateTimeImmutable):array{assignment_id:string,status:string} $action
     * @return array{assignment_id:string,status:string,replayed:bool}
     */
    private function transact(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $submission, string $operation, UuidV7 $appealId, int $version, callable $action): array
    {
        $this->allow($actor, $tenant);
        $this->rate($actor);
        return $this->transactions->transactional(function () use ($actor, $tenant, $submission, $operation, $appealId, $version, $action): array {
            $fingerprint = $this->fingerprint($tenant, $actor, $operation, $appealId, $version);
            $done = $this->completed($submission, $fingerprint);
            if ($done !== null) {
                return ['assignment_id' => $done['aggregate_id'], 'status' => $done['status'], 'replayed' => true];
            }
            $appeal = $this->lockAppeal($tenant->workspaceInternalId, $appealId);
            if ($appeal === null || !in_array($appeal['status'], ['SUBMITTED','UNDER_REVIEW'], true)) {
                throw new \DomainException('Appeal is unavailable for review assignment.');
            }
            $result = $action($appeal, $this->clock->now());
            $this->record($submission, $fingerprint, $operation, $tenant->workspaceInternalId, UuidV7::fromString($result['assignment_id']), $result['status'], 1, $this->clock->now());
            return $result + ['replayed' => false];
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    private function allow(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant): void
    {
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode('workspace.competitions.adjudicate_appeals'), new WorkspaceAuthorizationScope($tenant)));
    }
    private function rate(AuthenticatedAccountContext $actor): void
    {
        $attempt = new IdentityRateLimitAttempt(IdentityRateLimitScope::COMPETITION_APPEAL_REVIEW_ACCOUNT, $this->fingerprints->generate('competition-p7-appeal-account', (string) $actor->accountInternalId), new IdentityRateLimitPolicy(60, 20, 60));
        if (!$this->rateLimits->consume([$attempt], $this->clock->now())->allowed) {
            throw new \DomainException('Appeal adjudication is temporarily unavailable.');
        }
    }
    private function fingerprint(AccountWorkspaceTenantContext $tenant, AuthenticatedAccountContext $actor, string $operation, UuidV7 $id, int $version): string
    {
        return $this->fingerprints->generate('competition-p7-appeal-operation', implode("\0", [$tenant->workspaceInternalId, $actor->accountInternalId, $operation, $id->toString(), $version]))->toBinary();
    }
    /** @return array{aggregate_id:string,status:string,version:int}|null */
    private function completed(UuidV7 $submission, string $fingerprint): ?array
    {
        $statement = $this->statement('SELECT request_fingerprint,aggregate_public_id,result_status,version_after FROM competition_p7_operations WHERE submission_id=:submission_id FOR UPDATE');
        $statement->execute([':submission_id' => $submission->toBinary()]);
        $row = $this->row($statement);
        if ($row === null) {
            return null;
        } if (!hash_equals(PdoResultReader::string($row, 'request_fingerprint'), $fingerprint)) {
            throw new \DomainException('Appeal submission conflicts with a prior request.');
        } return ['aggregate_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'aggregate_public_id'))->toString(), 'status' => PdoResultReader::string($row, 'result_status'), 'version' => PdoResultReader::integer($row, 'version_after')];
    }
    /** @return array{id:int,workspace_id:int,result_run_id:int,status:string,version:int}|null */
    private function lockAppeal(int $workspaceId, UuidV7 $appealId): ?array
    {
        $statement = $this->statement('SELECT id,workspace_id,result_run_id,status,version FROM competition_appeals WHERE workspace_id=:workspace_id AND public_id=:public_id FOR UPDATE');
        $statement->execute([':workspace_id' => $workspaceId, ':public_id' => $appealId->toBinary()]);
        $row = $this->row($statement);
        return $row !== null ? ['id' => PdoResultReader::integer($row, 'id'), 'workspace_id' => PdoResultReader::integer($row, 'workspace_id'), 'result_run_id' => PdoResultReader::integer($row, 'result_run_id'), 'status' => PdoResultReader::string($row, 'status'), 'version' => PdoResultReader::integer($row, 'version')] : null;
    }
    /** @return array{id:int,reviewer_account_id:int,status:string,version:int}|null */
    private function lockAssignment(int $workspaceId, UuidV7 $assignmentId): ?array
    {
        $statement = $this->statement('SELECT id,reviewer_account_id,status,version FROM competition_appeal_review_assignments WHERE workspace_id=:workspace_id AND public_id=:public_id FOR UPDATE');
        $statement->execute([':workspace_id' => $workspaceId, ':public_id' => $assignmentId->toBinary()]);
        $row = $this->row($statement);
        return $row !== null ? ['id' => PdoResultReader::integer($row, 'id'), 'reviewer_account_id' => PdoResultReader::integer($row, 'reviewer_account_id'), 'status' => PdoResultReader::string($row, 'status'), 'version' => PdoResultReader::integer($row, 'version')] : null;
    }
    private function requireIndependentReviewers(int $workspaceId, int $appealId, int $actorId): void
    {
        $statement = $this->statement("SELECT COUNT(DISTINCT reviewer_account_id) FROM competition_appeal_review_assignments assignment WHERE workspace_id=:workspace_id AND appeal_id=:appeal_id AND status='ACCEPTED' AND NOT EXISTS (SELECT 1 FROM competition_appeal_reviewer_conflicts conflict WHERE conflict.workspace_id=assignment.workspace_id AND conflict.review_assignment_id=assignment.id AND conflict.status IN ('DECLARED','RECUSED'))");
        $statement->execute([':workspace_id' => $workspaceId, ':appeal_id' => $appealId]);
        if ((int) $statement->fetchColumn() < 2) {
            throw new \DomainException('Two independent, conflict-free accepted reviewers are required.');
        } $own = $this->statement("SELECT COUNT(*) FROM competition_appeal_review_assignments WHERE workspace_id=:workspace_id AND appeal_id=:appeal_id AND reviewer_account_id=:account_id AND status='ACCEPTED'");
        $own->execute([':workspace_id' => $workspaceId, ':appeal_id' => $appealId, ':account_id' => $actorId]);
        if ((int) $own->fetchColumn() !== 1) {
            throw new \DomainException('Only an accepted reviewer may record the adjudication.');
        }
    }
    /** @param array{id:int,workspace_id:int,result_run_id:int,status:string,version:int} $appeal */
    private function placePublicationHold(array $appeal, int $actorId, string $reasonCode, DateTimeImmutable $now): void
    {
        $publication = $this->statement("SELECT id,public_id,status,version FROM competition_result_publications WHERE workspace_id=:workspace_id AND result_run_id=:result_run_id AND current_public_marker=1 AND status='PROVISIONAL_PUBLISHED' FOR UPDATE");
        $publication->execute([':workspace_id' => $appeal['workspace_id'], ':result_run_id' => $appeal['result_run_id']]);
        $row = $this->row($publication);
        if ($row === null) {
            return;
        } $publicationId = PdoResultReader::integer($row, 'id');
        $publicationVersion = PdoResultReader::integer($row, 'version');
        $time = $this->time($now);
        $update = $this->statement("UPDATE competition_result_publications SET status='HELD',public_visibility='PRIVATE',current_public_marker=NULL,version=version+1,hold_reason_code=:reason_code,held_at=:held_at,updated_at=:updated_at WHERE id=:id AND workspace_id=:workspace_id AND status='PROVISIONAL_PUBLISHED' AND version=:version");
        $update->execute([':reason_code' => $reasonCode, ':held_at' => $time, ':updated_at' => $time, ':id' => $publicationId, ':workspace_id' => $appeal['workspace_id'], ':version' => $publicationVersion]);
        if ($update->rowCount() !== 1) {
            throw new \DomainException('Publication changed concurrently while the appeal hold was placed.');
        } $hold = $this->statement("INSERT INTO competition_result_publication_holds (public_id,workspace_id,publication_id,appeal_id,action,hold_type,safe_reason_code,actor_type,actor_account_id,occurred_at,created_at) VALUES (:public_id,:workspace_id,:publication_id,:appeal_id,'PLACED','APPEAL',:reason_code,'ACCOUNT',:actor_account_id,:occurred_at,:created_at)");
        $hold->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $appeal['workspace_id'], ':publication_id' => $publicationId, ':appeal_id' => $appeal['id'], ':reason_code' => $reasonCode, ':actor_account_id' => $actorId, ':occurred_at' => $time, ':created_at' => $time]);
        $event = $this->statement("INSERT INTO competition_result_publication_events (public_id,workspace_id,publication_id,event_type,from_status,to_status,actor_type,actor_account_id,safe_reason_code,result_run_sha256,publication_sha256,correlation_id,occurred_at,created_at) SELECT :public_id,:workspace_id,p.id,'HELD','PROVISIONAL_PUBLISHED','HELD','ACCOUNT',:actor_account_id,:reason_code,r.result_checksum_sha256,p.projection_sha256,:correlation_id,:occurred_at,:created_at FROM competition_result_publications p INNER JOIN competition_result_runs r ON r.workspace_id=p.workspace_id AND r.id=p.result_run_id WHERE p.id=:publication_id AND p.workspace_id=:event_workspace_id");
        $event->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $appeal['workspace_id'], ':actor_account_id' => $actorId, ':reason_code' => $reasonCode, ':correlation_id' => UuidV7::generate()->toBinary(), ':occurred_at' => $time, ':created_at' => $time, ':publication_id' => $publicationId, ':event_workspace_id' => $appeal['workspace_id']]);
    }
    private function record(UuidV7 $submission, string $fingerprint, string $operation, int $workspaceId, UuidV7 $aggregateId, string $status, int $version, DateTimeImmutable $now): void
    {
        $statement = $this->statement("INSERT INTO competition_p7_operations (public_id,submission_id,workspace_id,operation_code,request_fingerprint,aggregate_kind,aggregate_public_id,result_status,version_after,occurred_at) VALUES (:public_id,:submission_id,:workspace_id,:operation_code,:fingerprint,'APPEAL_ADJUDICATION',:aggregate_public_id,:result_status,:version_after,:occurred_at)");
        $statement->execute([':public_id' => UuidV7::generate()->toBinary(), ':submission_id' => $submission->toBinary(), ':workspace_id' => $workspaceId, ':operation_code' => $operation, ':fingerprint' => $fingerprint, ':aggregate_public_id' => $aggregateId->toBinary(), ':result_status' => $status, ':version_after' => $version, ':occurred_at' => $this->time($now)]);
    }
    private function statement(string $sql): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Appeal adjudication statement could not be prepared.');
        } return $statement;
    }
    /** @return array<string,mixed>|null */
    private function row(PDOStatement $statement): ?array
    {
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        } foreach ($row as $key => $_) {
            if (!is_string($key)) {
                throw new \UnexpectedValueException('Appeal adjudication row is invalid.');
            }
        } return $row;
    }
    private function time(DateTimeImmutable $now): string
    {
        return $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
