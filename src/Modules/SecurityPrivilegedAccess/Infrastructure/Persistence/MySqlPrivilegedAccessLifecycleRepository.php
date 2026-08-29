<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessApprovalCommand;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessApprovalResult;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessLifecycleRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessRequestSnapshot;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessApprovalDecision;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessApprovalType;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestStatus;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/**
 * Transactional storage for immutable approvals and terminal request cancellation.
 *
 * The caller opens the transaction, consumes a locked action-bound step-up grant,
 * then invokes these methods. Every mutable request row is selected FOR UPDATE.
 */
final readonly class MySqlPrivilegedAccessLifecycleRepository implements PrivilegedAccessLifecycleRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function requestSnapshot(PrivilegedAccessRequestId $requestId): ?PrivilegedAccessRequestSnapshot
    {
        $statement = $this->pdo()->prepare(
            'SELECT public_id, access_type, scope_type, status, subject_account_id, requested_by_account_id, '
            . 'workspace_id, subject_membership_id FROM privileged_access_requests WHERE public_id = :public_id LIMIT 1',
        );
        $statement->bindValue(':public_id', $requestId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();

        return self::snapshot(self::row($statement->fetch(PDO::FETCH_ASSOC)));
    }

    public function hasOverdueReviewForSubject(int $subjectAccountInternalId, PrivilegedAccessType $type): bool
    {
        $statement = $this->pdo()->prepare(
            "SELECT 1 FROM privileged_access_reviews review_record INNER JOIN privileged_access_requests request_record "
            . "ON request_record.id = review_record.request_id WHERE request_record.subject_account_id = :account_id "
            . "AND request_record.access_type = :access_type AND review_record.status = 'OVERDUE' LIMIT 1",
        );
        $statement->execute([':account_id' => $subjectAccountInternalId, ':access_type' => $type->value]);

        return $statement->fetchColumn() !== false;
    }

    public function approve(
        PrivilegedAccessApprovalCommand $command,
        int $stepUpGrantInternalId,
        DateTimeImmutable $now,
    ): PrivilegedAccessApprovalResult {
        $request = $this->lockedRequest($command->requestId);
        $type = PrivilegedAccessType::from(self::string($request, 'access_type'));
        $scope = AuthorizationScopeType::from(self::string($request, 'scope_type'));
        $status = PrivilegedAccessRequestStatus::from(self::string($request, 'status'));
        $this->assertApprovalEligible($command, $request, $type, $scope, $status, $now);

        $timestamp = self::format($now);
        $requestInternalId = self::integer($request, 'id');
        $existing = $this->approval($requestInternalId, $command->approvalType, true);
        if ($existing !== null) {
            throw new \DomainException('This privileged-access approval has already been decided.');
        }
        if ($type === PrivilegedAccessType::SUPPORT_ACCESS && $command->approvalType === PrivilegedAccessApprovalType::WORKSPACE) {
            $platformApproval = $this->approval($requestInternalId, PrivilegedAccessApprovalType::PLATFORM, true);
            if ($platformApproval !== null && self::integer($platformApproval, 'approver_account_id') === $command->actor->accountInternalId) {
                throw new \DomainException('Support platform and workspace approvals require distinct accounts.');
            }
        }
        $this->insertApproval($command, $requestInternalId, $stepUpGrantInternalId, $timestamp);

        if ($command->decision === PrivilegedAccessApprovalDecision::REJECTED) {
            $this->updateRequest(
                $requestInternalId,
                PrivilegedAccessRequestStatus::REJECTED,
                $timestamp,
                null,
                'rejected_at',
            );
            $this->appendEvent(
                $requestInternalId,
                null,
                'REJECTED',
                $command->actor->accountInternalId,
                $command->reason->value,
                $command->correlationId->value(),
                $timestamp
            );

            return new PrivilegedAccessApprovalResult(
                $command->requestId,
                $command->decision,
                PrivilegedAccessRequestStatus::REJECTED,
                null,
            );
        }

        [$nextStatus, $approvedDuration] = $this->approvedTransition($type, $requestInternalId, $command->approvedDuration->seconds);
        $timestampColumn = $nextStatus === PrivilegedAccessRequestStatus::APPROVED ? 'approved_at' : null;
        $this->updateRequest($requestInternalId, $nextStatus, $timestamp, $approvedDuration, $timestampColumn);
        $this->appendEvent(
            $requestInternalId,
            null,
            $nextStatus === PrivilegedAccessRequestStatus::APPROVED ? 'APPROVED' : 'PARTIALLY_APPROVED',
            $command->actor->accountInternalId,
            $command->reason->value,
            $command->correlationId->value(),
            $timestamp,
        );

        return new PrivilegedAccessApprovalResult($command->requestId, $command->decision, $nextStatus, $approvedDuration);
    }

    public function cancel(
        AuthenticatedAccountContext $actor,
        PrivilegedAccessRequestId $requestId,
        string $correlationId,
        DateTimeImmutable $now,
    ): PrivilegedAccessRequestSnapshot {
        $request = $this->lockedRequest($requestId);
        $snapshot = self::snapshot($request);
        if (
            $snapshot === null || $snapshot->subjectAccountInternalId !== $actor->accountInternalId
            || !in_array($snapshot->status, [PrivilegedAccessRequestStatus::REQUESTED, PrivilegedAccessRequestStatus::PARTIALLY_APPROVED], true)
        ) {
            throw new \DomainException('The privileged-access request cannot be cancelled.');
        }
        $timestamp = self::format($now);
        $this->updateRequest(self::integer($request, 'id'), PrivilegedAccessRequestStatus::CANCELLED, $timestamp, null, 'cancelled_at');
        $this->appendEvent(
            self::integer($request, 'id'),
            null,
            'CANCELLED',
            $actor->accountInternalId,
            'REQUEST_CANCELLED',
            $correlationId,
            $timestamp
        );

        return new PrivilegedAccessRequestSnapshot(
            $snapshot->id,
            $snapshot->type,
            $snapshot->scope,
            PrivilegedAccessRequestStatus::CANCELLED,
            $snapshot->subjectAccountInternalId,
            $snapshot->requestedByAccountInternalId,
            $snapshot->workspaceInternalId,
            $snapshot->subjectMembershipInternalId,
        );
    }

    public function endActive(
        AuthenticatedAccountContext $actor,
        string $correlationId,
        DateTimeImmutable $now,
    ): ?PrivilegedAccessRequestSnapshot {
        $statement = $this->pdo()->prepare(
            'SELECT a.id AS activation_id, a.status AS activation_status, r.id, r.public_id, r.access_type, r.scope_type, '
            . 'r.workspace_id, r.subject_account_id, r.subject_membership_id, r.requested_by_account_id, r.status '
            . 'FROM privileged_access_activations a INNER JOIN privileged_access_requests r ON r.id = a.request_id '
            . "WHERE a.subject_account_id = :account_id AND a.session_id = :session_id AND a.status = 'ACTIVE' "
            . 'LIMIT 1 FOR UPDATE',
        );
        $statement->bindValue(':account_id', $actor->accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':session_id', $actor->sessionInternalId, PDO::PARAM_INT);
        $statement->execute();
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }
        $snapshot = self::snapshot($row);
        if ($snapshot === null || $snapshot->status !== PrivilegedAccessRequestStatus::ACTIVE) {
            throw new \DomainException('The active privileged-access record is inconsistent.');
        }
        $timestamp = self::format($now);
        $activation = $this->pdo()->prepare(
            "UPDATE privileged_access_activations SET status = 'ENDED', ended_at = :ended_at, version = version + 1, "
            . 'updated_at = :updated_at WHERE id = :id AND status = \'ACTIVE\'',
        );
        $activation->execute([':ended_at' => $timestamp, ':updated_at' => $timestamp, ':id' => self::integer($row, 'activation_id')]);
        if ($activation->rowCount() !== 1) {
            throw new \DomainException('The active privileged-access record changed before it could be ended.');
        }
        $next = $snapshot->type === PrivilegedAccessType::TEMPORARY_PRIVILEGE
            ? PrivilegedAccessRequestStatus::CLOSED : PrivilegedAccessRequestStatus::REVIEW_REQUIRED;
        $this->updateRequest(
            self::integer($row, 'id'),
            $next,
            $timestamp,
            null,
            $next === PrivilegedAccessRequestStatus::CLOSED ? 'closed_at' : 'review_required_at',
        );
        $this->appendEvent(
            self::integer($row, 'id'),
            self::integer($row, 'activation_id'),
            'ENDED',
            $actor->accountInternalId,
            'ACCESS_ENDED_BY_SUBJECT',
            $correlationId,
            $timestamp
        );

        return new PrivilegedAccessRequestSnapshot(
            $snapshot->id,
            $snapshot->type,
            $snapshot->scope,
            $next,
            $snapshot->subjectAccountInternalId,
            $snapshot->requestedByAccountInternalId,
            $snapshot->workspaceInternalId,
            $snapshot->subjectMembershipInternalId,
        );
    }

    public function revoke(
        AuthenticatedAccountContext $actor,
        PrivilegedAccessRequestId $requestId,
        string $correlationId,
        DateTimeImmutable $now,
    ): PrivilegedAccessRequestSnapshot {
        $request = $this->lockedRequest($requestId);
        $snapshot = self::snapshot($request);
        if ($snapshot === null || !in_array($snapshot->status, [PrivilegedAccessRequestStatus::APPROVED, PrivilegedAccessRequestStatus::ACTIVE], true)) {
            throw new \DomainException('The privileged-access request cannot be revoked.');
        }
        $timestamp = self::format($now);
        $this->updateRequest(self::integer($request, 'id'), PrivilegedAccessRequestStatus::REVOKED, $timestamp, null, 'revoked_at');
        $activation = $this->pdo()->prepare(
            "SELECT id, session_id FROM privileged_access_activations WHERE request_id = :request_id AND status = 'ACTIVE' "
            . 'LIMIT 1 FOR UPDATE',
        );
        $activation->execute([':request_id' => self::integer($request, 'id')]);
        $active = self::row($activation->fetch(PDO::FETCH_ASSOC));
        if ($active !== null) {
            $update = $this->pdo()->prepare(
                "UPDATE privileged_access_activations SET status = 'REVOKED', revoked_at = :revoked_at, "
                . "revoke_reason_code = 'ACCESS_REVOKED', version = version + 1, updated_at = :updated_at "
                . "WHERE id = :id AND status = 'ACTIVE'",
            );
            $update->execute([':revoked_at' => $timestamp, ':updated_at' => $timestamp, ':id' => self::integer($active, 'id')]);
            if ($update->rowCount() !== 1) {
                throw new \DomainException('The active privileged-access record changed before it could be revoked.');
            }
            $clear = $this->pdo()->prepare(
                'UPDATE user_sessions SET selected_workspace_id = NULL, selected_membership_id = NULL, '
                . 'tenant_context_selected_at = NULL, tenant_context_version = tenant_context_version + 1, updated_at = :updated_at '
                . 'WHERE id = :session_id AND status = \'ACTIVE\'',
            );
            $clear->execute([':updated_at' => $timestamp, ':session_id' => self::integer($active, 'session_id')]);
        }
        $this->appendEvent(
            self::integer($request, 'id'),
            $active === null ? null : self::integer($active, 'id'),
            'REVOKED',
            $actor->accountInternalId,
            'ACCESS_REVOKED',
            $correlationId,
            $timestamp
        );

        return new PrivilegedAccessRequestSnapshot(
            $snapshot->id,
            $snapshot->type,
            $snapshot->scope,
            PrivilegedAccessRequestStatus::REVOKED,
            $snapshot->subjectAccountInternalId,
            $snapshot->requestedByAccountInternalId,
            $snapshot->workspaceInternalId,
            $snapshot->subjectMembershipInternalId,
        );
    }

    public function reviewSnapshot(\Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReviewId $reviewId): ?\Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessReviewSnapshot
    {
        $statement = $this->pdo()->prepare(
            'SELECT v.public_id, v.status, r.access_type, r.subject_account_id FROM privileged_access_reviews v '
            . 'INNER JOIN privileged_access_requests r ON r.id = v.request_id WHERE v.public_id = :public_id LIMIT 1',
        );
        $statement->bindValue(':public_id', $reviewId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }

        return new \Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessReviewSnapshot(
            \Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReviewId::fromBinary(self::string($row, 'public_id')),
            \Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReviewStatus::from(self::string($row, 'status')),
            PrivilegedAccessType::from(self::string($row, 'access_type')),
            self::integer($row, 'subject_account_id'),
        );
    }

    public function completeReview(
        \Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessReviewCommand $command,
        int $stepUpGrantInternalId,
        DateTimeImmutable $now,
    ): \Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessReviewResult {
        if ($stepUpGrantInternalId < 1) {
            throw new \InvalidArgumentException('A consumed step-up grant is required for review.');
        }
        $statement = $this->pdo()->prepare(
            'SELECT v.id AS review_id, v.status AS review_status, v.activation_id, r.id AS request_id, r.access_type, '
            . 'r.status AS request_status, r.subject_account_id FROM privileged_access_reviews v '
            . 'INNER JOIN privileged_access_requests r ON r.id = v.request_id WHERE v.public_id = :public_id LIMIT 1 FOR UPDATE',
        );
        $statement->bindValue(':public_id', $command->reviewId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if (
            $row === null || !in_array(self::string($row, 'review_status'), ['PENDING', 'OVERDUE'], true)
            || $command->actor->accountInternalId === self::integer($row, 'subject_account_id')
            || !in_array(self::string($row, 'access_type'), ['SUPPORT_ACCESS', 'BREAK_GLASS'], true)
        ) {
            throw new \DomainException('The privileged-access review is not eligible for completion.');
        }
        $timestamp = self::format($now);
        $review = $this->pdo()->prepare(
            "UPDATE privileged_access_reviews SET status = 'COMPLETED', reviewer_account_id = :reviewer_account_id, "
            . 'outcome = :outcome, review_summary = :review_summary, reviewed_at = :reviewed_at, version = version + 1, '
            . 'updated_at = :updated_at WHERE id = :id AND status IN (\'PENDING\', \'OVERDUE\')',
        );
        $review->execute([
            ':reviewer_account_id' => $command->actor->accountInternalId,
            ':outcome' => $command->outcome->value,
            ':review_summary' => $command->summary,
            ':reviewed_at' => $timestamp,
            ':updated_at' => $timestamp,
            ':id' => self::integer($row, 'review_id'),
        ]);
        if ($review->rowCount() !== 1) {
            throw new \DomainException('The privileged-access review changed before completion.');
        }
        if (self::string($row, 'request_status') === 'REVIEW_REQUIRED') {
            $close = $this->pdo()->prepare(
                "UPDATE privileged_access_requests SET status = 'CLOSED', closed_at = :closed_at, version = version + 1, "
                . 'updated_at = :updated_at WHERE id = :id AND status = \'REVIEW_REQUIRED\'',
            );
            $close->execute([':closed_at' => $timestamp, ':updated_at' => $timestamp, ':id' => self::integer($row, 'request_id')]);
            if ($close->rowCount() !== 1) {
                throw new \DomainException('The privileged-access request changed before review closure.');
            }
        }
        $reason = match ($command->outcome->value) {
            'CONCERN' => 'REVIEW_CONCERN',
            'ESCALATED' => 'REVIEW_ESCALATION',
            default => 'SUPPORT_REQUEST',
        };
        $this->appendEvent(
            self::integer($row, 'request_id'),
            self::integer($row, 'activation_id'),
            'REVIEW_COMPLETED',
            $command->actor->accountInternalId,
            $reason,
            $command->correlationId->value(),
            $timestamp
        );

        return new \Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessReviewResult($command->reviewId, $command->outcome);
    }

    /** @return array{PrivilegedAccessRequestStatus, int} */
    private function approvedTransition(PrivilegedAccessType $type, int $requestInternalId, int $currentDuration): array
    {
        if ($type === PrivilegedAccessType::TEMPORARY_PRIVILEGE) {
            return [PrivilegedAccessRequestStatus::APPROVED, $currentDuration];
        }
        $platform = $this->approval($requestInternalId, PrivilegedAccessApprovalType::PLATFORM, true);
        $workspace = $this->approval($requestInternalId, PrivilegedAccessApprovalType::WORKSPACE, true);
        if ($platform === null || $workspace === null) {
            return [PrivilegedAccessRequestStatus::PARTIALLY_APPROVED, $currentDuration];
        }
        if (self::string($platform, 'decision') !== 'APPROVED' || self::string($workspace, 'decision') !== 'APPROVED') {
            throw new \DomainException('A rejected approval cannot result in support activation.');
        }

        return [
            PrivilegedAccessRequestStatus::APPROVED,
            min(self::integer($platform, 'approved_duration_seconds'), self::integer($workspace, 'approved_duration_seconds')),
        ];
    }

    /** @param array<string, mixed> $request */
    private function assertApprovalEligible(
        PrivilegedAccessApprovalCommand $command,
        array $request,
        PrivilegedAccessType $type,
        AuthorizationScopeType $scope,
        PrivilegedAccessRequestStatus $status,
        DateTimeImmutable $now,
    ): void {
        if (
            !in_array($status, [PrivilegedAccessRequestStatus::REQUESTED, PrivilegedAccessRequestStatus::PARTIALLY_APPROVED], true)
            || self::string($request, 'request_expires_at') <= self::format($now)
        ) {
            throw new \DomainException('The privileged-access request is not eligible for approval.');
        }
        $isValid = match ($command->approvalType) {
            PrivilegedAccessApprovalType::PLATFORM => ($type === PrivilegedAccessType::TEMPORARY_PRIVILEGE && $scope === AuthorizationScopeType::PLATFORM)
                || $type === PrivilegedAccessType::SUPPORT_ACCESS,
            PrivilegedAccessApprovalType::WORKSPACE => ($type === PrivilegedAccessType::TEMPORARY_PRIVILEGE && $scope === AuthorizationScopeType::WORKSPACE)
                || $type === PrivilegedAccessType::SUPPORT_ACCESS,
        };
        if (!$isValid || $type === PrivilegedAccessType::BREAK_GLASS) {
            throw new \DomainException('This privileged-access request does not support the selected approval.');
        }
        if (
            $command->actor->accountInternalId === self::integer($request, 'subject_account_id')
            || $command->actor->accountInternalId === self::integer($request, 'requested_by_account_id')
        ) {
            throw new \DomainException('A requestor or subject cannot approve their own privileged access.');
        }
        if ($command->approvedDuration->seconds > self::integer($request, 'requested_duration_seconds')) {
            throw new \DomainException('The approved duration exceeds the requested duration.');
        }
        if (
            $command->approvalType === PrivilegedAccessApprovalType::WORKSPACE
            && ($command->workspaceInternalId !== self::integer($request, 'workspace_id')
                || $command->approverMembershipInternalId === null)
        ) {
            throw new \DomainException('The workspace approval context does not match this request.');
        }
    }

    private function insertApproval(
        PrivilegedAccessApprovalCommand $command,
        int $requestInternalId,
        int $stepUpGrantInternalId,
        string $timestamp,
    ): void {
        $statement = $this->pdo()->prepare(<<<'SQL'
INSERT INTO privileged_access_approvals
    (public_id, request_id, approval_type, decision, approver_account_id, workspace_id, approver_membership_id,
     assurance_level, step_up_grant_id, approved_duration_seconds, reason_code, decided_at, created_at)
VALUES
    (:public_id, :request_id, :approval_type, :decision, :approver_account_id, :workspace_id, :membership_id,
     :assurance_level, :step_up_grant_id, :duration, :reason_code, :decided_at, :created_at)
SQL);
        $statement->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':request_id', $requestInternalId, PDO::PARAM_INT);
        $statement->bindValue(':approval_type', $command->approvalType->value);
        $statement->bindValue(':decision', $command->decision->value);
        $statement->bindValue(':approver_account_id', $command->actor->accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':workspace_id', $command->workspaceInternalId, $command->workspaceInternalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':membership_id', $command->approverMembershipInternalId, $command->approverMembershipInternalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':assurance_level', $command->actor->assurance->level->value);
        $statement->bindValue(':step_up_grant_id', $stepUpGrantInternalId, PDO::PARAM_INT);
        $statement->bindValue(':duration', $command->approvedDuration->seconds, PDO::PARAM_INT);
        $statement->bindValue(':reason_code', $command->reason->value);
        $statement->bindValue(':decided_at', $timestamp);
        $statement->bindValue(':created_at', $timestamp);
        $statement->execute();
    }

    private function updateRequest(
        int $requestInternalId,
        PrivilegedAccessRequestStatus $status,
        string $timestamp,
        ?int $approvedDuration,
        ?string $stateTimestampColumn,
    ): void {
        $sets = ["status = :status", 'version = version + 1', 'updated_at = :updated_at'];
        if ($approvedDuration !== null) {
            $sets[] = 'approved_duration_seconds = :approved_duration';
        }
        if ($stateTimestampColumn !== null) {
            $sets[] = $stateTimestampColumn . ' = :state_at';
        }
        $statement = $this->pdo()->prepare('UPDATE privileged_access_requests SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $statement->bindValue(':status', $status->value);
        $statement->bindValue(':updated_at', $timestamp);
        if ($approvedDuration !== null) {
            $statement->bindValue(':approved_duration', $approvedDuration, PDO::PARAM_INT);
        }
        if ($stateTimestampColumn !== null) {
            $statement->bindValue(':state_at', $timestamp);
        }
        $statement->bindValue(':id', $requestInternalId, PDO::PARAM_INT);
        $statement->execute();
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('The privileged-access request changed before its lifecycle transition.');
        }
    }

    /** @return array<string, mixed>|null */
    private function approval(int $requestInternalId, PrivilegedAccessApprovalType $type, bool $forUpdate): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT id, decision, approver_account_id, approved_duration_seconds FROM privileged_access_approvals '
            . 'WHERE request_id = :request_id AND approval_type = :approval_type LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->bindValue(':request_id', $requestInternalId, PDO::PARAM_INT);
        $statement->bindValue(':approval_type', $type->value);
        $statement->execute();

        return self::row($statement->fetch(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    private function lockedRequest(PrivilegedAccessRequestId $requestId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT id, public_id, access_type, scope_type, workspace_id, subject_account_id, subject_membership_id, '
            . 'requested_by_account_id, status, requested_duration_seconds, request_expires_at FROM privileged_access_requests '
            . 'WHERE public_id = :public_id LIMIT 1 FOR UPDATE',
        );
        $statement->bindValue(':public_id', $requestId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            throw new \DomainException('The privileged-access request is unavailable.');
        }

        return $row;
    }

    private function appendEvent(
        int $requestInternalId,
        ?int $activationInternalId,
        string $eventType,
        ?int $actorAccountInternalId,
        string $reasonCode,
        string $correlationId,
        string $timestamp,
    ): void {
        $correlation = hex2bin($correlationId);
        if (!is_string($correlation)) {
            throw new \InvalidArgumentException('Privileged-access correlation identifier is invalid.');
        }
        $statement = $this->pdo()->prepare(<<<'SQL'
INSERT INTO privileged_access_events
    (public_id, request_id, activation_id, event_type, actor_kind, actor_account_id, reason_code, correlation_id, occurred_at)
VALUES
    (:public_id, :request_id, :activation_id, :event_type, :actor_kind, :actor_account_id, :reason_code,
     :correlation_id, :occurred_at)
SQL);
        $statement->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':request_id', $requestInternalId, PDO::PARAM_INT);
        $statement->bindValue(':activation_id', $activationInternalId, $activationInternalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':event_type', $eventType);
        $statement->bindValue(':actor_kind', $actorAccountInternalId === null ? 'SYSTEM' : 'ACCOUNT');
        $statement->bindValue(':actor_account_id', $actorAccountInternalId, $actorAccountInternalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':reason_code', $reasonCode);
        $statement->bindValue(':correlation_id', $correlation, PDO::PARAM_LOB);
        $statement->bindValue(':occurred_at', $timestamp);
        $statement->execute();
    }

    /** @param array<string, mixed>|null $row */
    private static function snapshot(?array $row): ?PrivilegedAccessRequestSnapshot
    {
        if ($row === null) {
            return null;
        }

        return new PrivilegedAccessRequestSnapshot(
            PrivilegedAccessRequestId::fromBinary(self::string($row, 'public_id')),
            PrivilegedAccessType::from(self::string($row, 'access_type')),
            AuthorizationScopeType::from(self::string($row, 'scope_type')),
            PrivilegedAccessRequestStatus::from(self::string($row, 'status')),
            self::integer($row, 'subject_account_id'),
            self::integer($row, 'requested_by_account_id'),
            self::nullableInteger($row, 'workspace_id'),
            self::nullableInteger($row, 'subject_membership_id'),
        );
    }

    private function pdo(): PDO
    {
        return $this->provider->connection();
    }

    private static function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @return array<string, mixed>|null */
    private static function row(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $row = [];
        foreach ($value as $column => $field) {
            if (!is_string($column)) {
                throw new \UnexpectedValueException('Privileged-access row is invalid.');
            }
            $row[$column] = $field;
        }

        return $row;
    }

    /** @param array<string, mixed> $row */
    private static function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException('Privileged-access row is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function integer(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || !ctype_digit($value)) {
            throw new \UnexpectedValueException('Privileged-access row is invalid.');
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $row */
    private static function nullableInteger(array $row, string $column): ?int
    {
        return ($row[$column] ?? null) === null ? null : self::integer($row, $column);
    }
}
