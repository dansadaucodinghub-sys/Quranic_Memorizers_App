<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessActivationRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessActivationId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlPrivilegedAccessActivationRepository implements PrivilegedAccessActivationRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function activate(
        AuthenticatedAccountContext $actor,
        PrivilegedAccessRequestId $requestId,
        PrivilegedAccessType $expectedType,
        int $tenantContextVersion,
        string $correlationId,
        DateTimeImmutable $now,
        ?DateTimeImmutable $reviewDueAt,
    ): PrivilegedAccessActivationId {
        $pdo = $this->provider->connection();
        $statement = $pdo->prepare(
            'SELECT request_record.id, request_record.access_type, request_record.scope_type, request_record.workspace_id, '
            . 'request_record.status, request_record.requested_duration_seconds, request_record.approved_duration_seconds, '
            . 'request_record.request_expires_at, account.account_status, session_record.status AS session_status, '
            . 'session_record.idle_expires_at, session_record.absolute_expires_at, workspace.status_code AS workspace_status, '
            . 'membership.status_code AS membership_status FROM privileged_access_requests request_record '
            . 'INNER JOIN user_accounts account ON account.id = request_record.subject_account_id '
            . 'INNER JOIN user_sessions session_record ON session_record.id = :session_id '
            . 'AND session_record.account_id = request_record.subject_account_id '
            . 'LEFT JOIN workspaces workspace ON workspace.id = request_record.workspace_id '
            . 'LEFT JOIN workspace_memberships membership ON membership.id = request_record.subject_membership_id '
            . 'WHERE request_record.public_id = :public_id AND request_record.subject_account_id = :account_id LIMIT 1 FOR UPDATE',
        );
        $statement->bindValue(':public_id', $requestId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':account_id', $actor->accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':session_id', $actor->sessionInternalId, PDO::PARAM_INT);
        $statement->execute();
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            throw new \DomainException('The privileged-access request is unavailable.');
        }
        $type = self::string($row, 'access_type');
        if ($type !== $expectedType->value) {
            throw new \DomainException('The privileged-access request type does not match the requested activation.');
        }
        $status = self::string($row, 'status');
        $scopeType = self::string($row, 'scope_type');
        $requiredAssurance = $type === 'TEMPORARY_PRIVILEGE' && $scopeType === 'WORKSPACE'
            ? \Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel::MULTI_FACTOR
            : \Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel::PHISHING_RESISTANT;
        if (!$actor->assurance->level->satisfies($requiredAssurance)) {
            throw new \DomainException('The current session assurance is insufficient for privileged activation.');
        }
        $nowText = self::format($now);
        if (
            self::string($row, 'account_status') !== 'ACTIVE'
            || self::string($row, 'session_status') !== 'ACTIVE'
            || self::string($row, 'idle_expires_at') <= $nowText
            || self::string($row, 'absolute_expires_at') <= $nowText
        ) {
            throw new \DomainException('The authenticated account session is no longer eligible for privileged activation.');
        }
        if (
            $scopeType === 'WORKSPACE' && (self::nullableString($row, 'workspace_status') !== 'ACTIVE'
            || (self::nullableString($row, 'membership_status') !== null
                && self::nullableString($row, 'membership_status') !== 'ACTIVE'))
        ) {
            throw new \DomainException('The requested workspace access is no longer eligible for activation.');
        }
        if (self::string($row, 'request_expires_at') <= $nowText) {
            throw new \DomainException('The privileged-access request has expired.');
        }
        if (!(($type === 'BREAK_GLASS' && $status === 'REQUESTED') || ($type !== 'BREAK_GLASS' && $status === 'APPROVED'))) {
            throw new \DomainException('The privileged-access request is not ready for activation.');
        }
        $this->assertPermissionsRemainEligible(self::integer($row, 'id'), $type);
        if ($type === 'SUPPORT_ACCESS') {
            $this->assertSupportApprovalsAreDistinct(self::integer($row, 'id'), $actor->accountInternalId);
        }
        $duration = self::nullableInteger($row, 'approved_duration_seconds') ?? self::integer($row, 'requested_duration_seconds');
        $activationId = PrivilegedAccessActivationId::generate();
        $activation = $pdo->prepare(<<<'SQL'
INSERT INTO privileged_access_activations
    (public_id, request_id, access_type, scope_type, subject_account_id, session_id, workspace_id,
     assurance_level, status, activated_at, expires_at, tenant_context_version_at_activation, version, created_at, updated_at)
VALUES
    (:public_id, :request_id, :access_type, :scope_type, :account_id, :session_id, :workspace_id,
     :assurance_level, 'ACTIVE', :activated_at, :expires_at, :tenant_context_version, 1, :created_at, :updated_at)
SQL);
        $activation->bindValue(':public_id', $activationId->toBinary(), PDO::PARAM_LOB);
        $activation->bindValue(':request_id', self::integer($row, 'id'), PDO::PARAM_INT);
        $activation->bindValue(':access_type', $type);
        $activation->bindValue(':scope_type', self::string($row, 'scope_type'));
        $activation->bindValue(':account_id', $actor->accountInternalId, PDO::PARAM_INT);
        $activation->bindValue(':session_id', $actor->sessionInternalId, PDO::PARAM_INT);
        $workspace = self::nullableInteger($row, 'workspace_id');
        $activation->bindValue(':workspace_id', $workspace, $workspace === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $activation->bindValue(':assurance_level', $actor->assurance->level->value);
        $activation->bindValue(':activated_at', $nowText);
        $activation->bindValue(':expires_at', self::format($now->modify('+' . $duration . ' seconds')));
        $activation->bindValue(':tenant_context_version', $tenantContextVersion, PDO::PARAM_INT);
        $activation->bindValue(':created_at', $nowText);
        $activation->bindValue(':updated_at', $nowText);
        $activation->execute();
        $activationInternalId = (int) $pdo->lastInsertId();
        if ($activationInternalId < 1) {
            throw new \UnexpectedValueException('Privileged-access activation persistence failed.');
        }
        $update = $pdo->prepare(
            "UPDATE privileged_access_requests SET status = 'ACTIVE', activated_at = :activated_at, version = version + 1, "
            . 'updated_at = :updated_at WHERE id = :id AND status = :expected_status',
        );
        $update->execute([':activated_at' => $nowText, ':updated_at' => $nowText, ':id' => self::integer($row, 'id'),
            ':expected_status' => $status]);
        if ($update->rowCount() !== 1) {
            throw new \DomainException('The privileged-access request changed before activation.');
        }
        $correlation = hex2bin($correlationId);
        if (!is_string($correlation)) {
            throw new \InvalidArgumentException('Privileged-access correlation identifier is invalid.');
        }
        $reason = match ($type) {
            'SUPPORT_ACCESS' => 'SUPPORT_REQUEST',
            'BREAK_GLASS' => 'INCIDENT_RESPONSE',
            default => 'TEMPORARY_OPERATIONAL_NEED',
        };
        $event = $pdo->prepare(<<<'SQL'
INSERT INTO privileged_access_events
    (public_id, request_id, activation_id, event_type, actor_kind, actor_account_id, reason_code, correlation_id, occurred_at)
VALUES (:public_id, :request_id, :activation_id, 'ACTIVATED', 'ACCOUNT', :account_id,
    :reason_code, :correlation_id, :occurred_at)
SQL);
        $event->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $event->bindValue(':request_id', self::integer($row, 'id'), PDO::PARAM_INT);
        $event->bindValue(':activation_id', $activationInternalId, PDO::PARAM_INT);
        $event->bindValue(':account_id', $actor->accountInternalId, PDO::PARAM_INT);
        $event->bindValue(':reason_code', $reason);
        $event->bindValue(':correlation_id', $correlation, PDO::PARAM_LOB);
        $event->bindValue(':occurred_at', $nowText);
        $event->execute();

        if ($reviewDueAt !== null) {
            $review = $pdo->prepare(<<<'SQL'
INSERT INTO privileged_access_reviews
    (public_id, request_id, activation_id, status, due_at, version, created_at, updated_at)
VALUES (:public_id, :request_id, :activation_id, 'PENDING', :due_at, 1, :created_at, :updated_at)
SQL);
            $review->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
            $review->bindValue(':request_id', self::integer($row, 'id'), PDO::PARAM_INT);
            $review->bindValue(':activation_id', $activationInternalId, PDO::PARAM_INT);
            $review->bindValue(':due_at', self::format($reviewDueAt));
            $review->bindValue(':created_at', $nowText);
            $review->bindValue(':updated_at', $nowText);
            $review->execute();
            $reviewEvent = $pdo->prepare(<<<'SQL'
INSERT INTO privileged_access_events
    (public_id, request_id, activation_id, event_type, actor_kind, actor_account_id, reason_code, correlation_id, occurred_at)
VALUES (:public_id, :request_id, :activation_id, 'REVIEW_CREATED', 'SYSTEM', NULL, :reason_code, :correlation_id, :occurred_at)
SQL);
            $reviewEvent->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
            $reviewEvent->bindValue(':request_id', self::integer($row, 'id'), PDO::PARAM_INT);
            $reviewEvent->bindValue(':activation_id', $activationInternalId, PDO::PARAM_INT);
            $reviewEvent->bindValue(':reason_code', $type === 'BREAK_GLASS' ? 'INCIDENT_RESPONSE' : 'SUPPORT_REQUEST');
            $reviewEvent->bindValue(':correlation_id', $correlation, PDO::PARAM_LOB);
            $reviewEvent->bindValue(':occurred_at', $nowText);
            $reviewEvent->execute();
        }

        return $activationId;
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

    /** @param array<string, mixed> $row */
    private static function nullableString(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new \UnexpectedValueException('Privileged-access row is invalid.');
        }

        return $value;
    }

    private function assertPermissionsRemainEligible(int $requestInternalId, string $accessType): void
    {
        $statement = $this->provider->connection()->prepare(<<<'SQL'
SELECT COUNT(*)
FROM privileged_access_request_permissions snapshot
LEFT JOIN privileged_access_permission_policies policy
    ON policy.permission_id = snapshot.permission_id
    AND policy.permission_scope_type = snapshot.permission_scope_type
    AND policy.access_type = :access_type
    AND policy.status = 'ACTIVE'
WHERE snapshot.request_id = :request_id AND policy.permission_id IS NULL
SQL);
        $statement->execute([':access_type' => $accessType, ':request_id' => $requestInternalId]);
        $invalid = $statement->fetchColumn();
        if (!is_int($invalid) && (!is_string($invalid) || !ctype_digit($invalid))) {
            throw new \UnexpectedValueException('Privileged-access permission eligibility cannot be verified.');
        }
        if ((int) $invalid !== 0) {
            throw new \DomainException('A requested privileged permission is no longer eligible.');
        }
        $count = $this->provider->connection()->prepare(
            'SELECT COUNT(*) FROM privileged_access_request_permissions WHERE request_id = :request_id',
        );
        $count->execute([':request_id' => $requestInternalId]);
        $value = $count->fetchColumn();
        if ((!is_int($value) && (!is_string($value) || !ctype_digit($value))) || (int) $value < 1) {
            throw new \DomainException('A privileged-access request requires at least one permission.');
        }
    }

    private function assertSupportApprovalsAreDistinct(int $requestInternalId, int $subjectAccountInternalId): void
    {
        $statement = $this->provider->connection()->prepare(
            "SELECT approval_type, decision, approver_account_id FROM privileged_access_approvals WHERE request_id = :request_id "
            . "AND approval_type IN ('PLATFORM', 'WORKSPACE') FOR UPDATE",
        );
        $statement->execute([':request_id' => $requestInternalId]);
        $approvers = [];
        while (is_array($row = $statement->fetch(PDO::FETCH_ASSOC))) {
            $approval = self::row($row);
            if ($approval === null || self::string($approval, 'decision') !== 'APPROVED') {
                throw new \DomainException('Support access requires approved platform and workspace decisions.');
            }
            $type = self::string($approval, 'approval_type');
            $approver = self::integer($approval, 'approver_account_id');
            if ($approver === $subjectAccountInternalId || isset($approvers[$type])) {
                throw new \DomainException('Support access approvals are not eligible for activation.');
            }
            $approvers[$type] = $approver;
        }
        if (
            !isset($approvers['PLATFORM'], $approvers['WORKSPACE'])
            || $approvers['PLATFORM'] === $approvers['WORKSPACE']
        ) {
            throw new \DomainException('Support access requires distinct platform and workspace approvals.');
        }
    }
}
