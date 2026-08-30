<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessMaintenanceNotification;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessMaintenanceRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessMaintenanceResult;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReviewId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlPrivilegedAccessMaintenanceRepository implements PrivilegedAccessMaintenanceRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function maintain(DateTimeImmutable $now, int $limit, int $reviewTtlSeconds): PrivilegedAccessMaintenanceResult
    {
        $limit = max(1, min(500, $limit));
        $timestamp = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $pdo = $this->provider->connection();
        if (!$this->schemaIsAvailable($pdo)) {
            return new PrivilegedAccessMaintenanceResult(0, 0, 0);
        }
        $notifications = [];
        $expiredRequests = $this->expireRequests($pdo, $timestamp, $limit);
        [$expiredActivations, $activationNotifications] = $this->expireActivations($pdo, $timestamp, $limit);
        $notifications = [...$notifications, ...$activationNotifications];
        $notifications = [...$notifications, ...$this->createRequiredReviews($pdo, $timestamp, $limit, $reviewTtlSeconds)];
        [$overdueReviews, $reviewNotifications] = $this->markReviewsOverdue($pdo, $timestamp, $limit);
        $notifications = [...$notifications, ...$reviewNotifications];

        return new PrivilegedAccessMaintenanceResult($expiredRequests, $expiredActivations, $overdueReviews, $notifications);
    }

    private function expireRequests(PDO $pdo, string $now, int $limit): int
    {
        $rows = $this->rows($pdo, <<<'SQL'
SELECT id, public_id
FROM privileged_access_requests
WHERE status IN ('REQUESTED','PARTIALLY_APPROVED','APPROVED') AND request_expires_at <= :now
ORDER BY id
LIMIT :row_limit
FOR UPDATE
SQL, $now, $limit);
        $update = $pdo->prepare(
            "UPDATE privileged_access_requests SET status = 'EXPIRED', expired_at = :now, closed_at = :now, "
            . 'version = version + 1, updated_at = :now WHERE id = :id',
        );
        foreach ($rows as $row) {
            $requestId = self::integer($row['id']);
            $update->execute([':now' => $now, ':id' => $requestId]);
            if ($update->rowCount() !== 1) {
                throw new \DomainException('The expired privileged-access request changed before maintenance completed.');
            }
            $this->appendSystemEvent($pdo, $requestId, null, 'EXPIRED', 'REQUEST_EXPIRED', $now);
        }

        return count($rows);
    }

    /** @return array{int, list<PrivilegedAccessMaintenanceNotification>} */
    private function expireActivations(PDO $pdo, string $now, int $limit): array
    {
        $rows = $this->rows($pdo, <<<'SQL'
SELECT activation.id AS activation_id, activation.session_id, request_record.id AS request_id,
       request_record.public_id AS request_public_id, request_record.access_type, request_record.scope_type,
       request_record.subject_account_id, workspace.public_id AS workspace_public_id
FROM privileged_access_activations activation
INNER JOIN privileged_access_requests request_record ON request_record.id = activation.request_id
LEFT JOIN workspaces workspace ON workspace.id = request_record.workspace_id
WHERE activation.status = 'ACTIVE' AND request_record.status = 'ACTIVE' AND activation.expires_at <= :now
ORDER BY activation.id
LIMIT :row_limit
FOR UPDATE
SQL, $now, $limit);
        $activationUpdate = $pdo->prepare(
            "UPDATE privileged_access_activations SET status = 'EXPIRED', ended_at = :now, version = version + 1, "
            . 'updated_at = :now WHERE id = :id AND status = \'ACTIVE\'',
        );
        $requestUpdate = $pdo->prepare(<<<'SQL'
UPDATE privileged_access_requests
SET status = CASE WHEN access_type = 'TEMPORARY_PRIVILEGE' THEN 'EXPIRED' ELSE 'REVIEW_REQUIRED' END,
    expired_at = CASE WHEN access_type = 'TEMPORARY_PRIVILEGE' THEN :now ELSE expired_at END,
    review_required_at = CASE WHEN access_type <> 'TEMPORARY_PRIVILEGE' THEN :now ELSE review_required_at END,
    closed_at = CASE WHEN access_type = 'TEMPORARY_PRIVILEGE' THEN :now ELSE closed_at END,
    version = version + 1,
    updated_at = :now
WHERE id = :id AND status = 'ACTIVE'
SQL);
        $clearContext = $pdo->prepare(
            'UPDATE user_sessions SET selected_workspace_id = NULL, selected_membership_id = NULL, '
            . 'tenant_context_selected_at = NULL, tenant_context_version = tenant_context_version + 1, updated_at = :now '
            . "WHERE id = :session_id AND status = 'ACTIVE'",
        );
        $notifications = [];
        foreach ($rows as $row) {
            $activationId = self::integer($row['activation_id']);
            $requestId = self::integer($row['request_id']);
            $activationUpdate->execute([':now' => $now, ':id' => $activationId]);
            if ($activationUpdate->rowCount() !== 1) {
                throw new \DomainException('The expired privileged-access activation changed before maintenance completed.');
            }
            $requestUpdate->execute([':now' => $now, ':id' => $requestId]);
            if ($requestUpdate->rowCount() !== 1) {
                throw new \DomainException('The expired privileged-access request changed before maintenance completed.');
            }
            $clearContext->execute([':now' => $now, ':session_id' => self::integer($row['session_id'])]);
            $this->appendSystemEvent($pdo, $requestId, $activationId, 'EXPIRED', 'ACCESS_EXPIRED', $now);
            $type = PrivilegedAccessType::from(self::string($row['access_type']));
            $source = PrivilegedAccessRequestId::fromBinary(self::string($row['request_public_id']))->toString();
            $subject = self::integer($row['subject_account_id']);
            $notifications[] = new PrivilegedAccessMaintenanceNotification(
                $subject,
                match ($type) {
                    PrivilegedAccessType::TEMPORARY_PRIVILEGE => AccountSecurityNotificationType::TEMPORARY_PRIVILEGE_EXPIRED,
                    PrivilegedAccessType::SUPPORT_ACCESS => AccountSecurityNotificationType::SUPPORT_ACCESS_ENDED,
                    PrivilegedAccessType::BREAK_GLASS => AccountSecurityNotificationType::BREAK_GLASS_EXPIRED,
                },
                $source,
                AuthorizationScopeType::from(self::string($row['scope_type'] ?? null)),
                self::nullableUuid($row, 'workspace_public_id'),
            );
        }

        return [count($rows), $notifications];
    }

    /** @return list<PrivilegedAccessMaintenanceNotification> */
    private function createRequiredReviews(PDO $pdo, string $now, int $limit, int $reviewTtlSeconds): array
    {
        $rows = $this->rows($pdo, <<<'SQL'
SELECT activation.id AS activation_id, request_record.id AS request_id, request_record.public_id AS request_public_id,
       request_record.access_type, request_record.subject_account_id
FROM privileged_access_activations activation
INNER JOIN privileged_access_requests request_record ON request_record.id = activation.request_id
LEFT JOIN privileged_access_reviews review_record ON review_record.activation_id = activation.id
WHERE activation.status IN ('ENDED','EXPIRED','REVOKED')
  AND request_record.status IN ('REVIEW_REQUIRED','REVOKED')
  AND request_record.access_type IN ('SUPPORT_ACCESS','BREAK_GLASS')
  AND review_record.id IS NULL
ORDER BY activation.id
LIMIT :row_limit
FOR UPDATE
SQL, $now, $limit);
        $insert = $pdo->prepare(
            "INSERT INTO privileged_access_reviews (public_id, request_id, activation_id, status, due_at, version, created_at, updated_at) "
            . "VALUES (:public_id, :request_id, :activation_id, 'PENDING', :due_at, 1, :now, :now)",
        );
        $notifications = [];
        foreach ($rows as $row) {
            $requestId = self::integer($row['request_id']);
            $activationId = self::integer($row['activation_id']);
            $insert->bindValue(':public_id', PrivilegedAccessReviewId::generate()->toBinary(), PDO::PARAM_LOB);
            $insert->bindValue(':request_id', $requestId, PDO::PARAM_INT);
            $insert->bindValue(':activation_id', $activationId, PDO::PARAM_INT);
            $insert->bindValue(':now', $now);
            $insert->bindValue(':due_at', (new DateTimeImmutable($now, new DateTimeZone('UTC')))
                ->modify('+' . $reviewTtlSeconds . ' seconds')->format('Y-m-d H:i:s.u'));
            $insert->execute();
            $this->appendSystemEvent($pdo, $requestId, $activationId, 'REVIEW_CREATED', 'REVIEW_REQUIRED', $now);
            $type = PrivilegedAccessType::from(self::string($row['access_type']));
            $notifications[] = new PrivilegedAccessMaintenanceNotification(
                self::integer($row['subject_account_id']),
                $type === PrivilegedAccessType::SUPPORT_ACCESS
                    ? AccountSecurityNotificationType::SUPPORT_ACCESS_REVIEW_REQUIRED
                    : AccountSecurityNotificationType::BREAK_GLASS_REVIEW_REQUIRED,
                PrivilegedAccessRequestId::fromBinary(self::string($row['request_public_id']))->toString(),
            );
        }

        return $notifications;
    }

    /** @return array{int, list<PrivilegedAccessMaintenanceNotification>} */
    private function markReviewsOverdue(PDO $pdo, string $now, int $limit): array
    {
        $rows = $this->rows($pdo, <<<'SQL'
SELECT review_record.id AS review_id, review_record.activation_id, request_record.id AS request_id,
       request_record.public_id AS request_public_id, request_record.access_type, request_record.subject_account_id
FROM privileged_access_reviews review_record
INNER JOIN privileged_access_requests request_record ON request_record.id = review_record.request_id
WHERE review_record.status = 'PENDING' AND review_record.due_at <= :now
ORDER BY review_record.id
LIMIT :row_limit
FOR UPDATE
SQL, $now, $limit);
        $update = $pdo->prepare(
            "UPDATE privileged_access_reviews SET status = 'OVERDUE', version = version + 1, updated_at = :now "
            . "WHERE id = :id AND status = 'PENDING'",
        );
        $notifications = [];
        foreach ($rows as $row) {
            $update->execute([':now' => $now, ':id' => self::integer($row['review_id'])]);
            if ($update->rowCount() !== 1) {
                throw new \DomainException('The privileged-access review changed before overdue maintenance completed.');
            }
            $requestId = self::integer($row['request_id']);
            $this->appendSystemEvent($pdo, $requestId, self::integer($row['activation_id']), 'REVIEW_OVERDUE', 'REVIEW_OVERDUE', $now);
            $type = PrivilegedAccessType::from(self::string($row['access_type']));
            $notifications[] = new PrivilegedAccessMaintenanceNotification(
                self::integer($row['subject_account_id']),
                $type === PrivilegedAccessType::SUPPORT_ACCESS
                    ? AccountSecurityNotificationType::SUPPORT_ACCESS_REVIEW_OVERDUE
                    : AccountSecurityNotificationType::BREAK_GLASS_REVIEW_OVERDUE,
                PrivilegedAccessRequestId::fromBinary(self::string($row['request_public_id']))->toString(),
            );
        }

        return [count($rows), $notifications];
    }

    /** @return list<array<string, mixed>> */
    private function rows(PDO $pdo, string $sql, string $now, int $limit): array
    {
        $statement = $pdo->prepare($sql);
        if (str_contains($sql, ':now')) {
            $statement->bindValue(':now', $now);
        }
        $statement->bindValue(':row_limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \UnexpectedValueException('Privileged-access maintenance row is invalid.');
            }
            $candidate = [];
            foreach ($row as $column => $value) {
                if (!is_string($column)) {
                    throw new \UnexpectedValueException('Privileged-access maintenance row is invalid.');
                }
                $candidate[$column] = $value;
            }
            $normalized[] = $candidate;
        }

        return $normalized;
    }

    private function appendSystemEvent(
        PDO $pdo,
        int $requestId,
        ?int $activationId,
        string $eventType,
        string $reasonCode,
        string $occurredAt,
    ): void {
        $event = $pdo->prepare(<<<'SQL'
INSERT INTO privileged_access_events
    (public_id, request_id, activation_id, event_type, actor_kind, actor_account_id, reason_code, correlation_id, occurred_at)
VALUES (:public_id, :request_id, :activation_id, :event_type, 'SYSTEM', NULL, :reason_code, :correlation_id, :occurred_at)
SQL);
        $event->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $event->bindValue(':request_id', $requestId, PDO::PARAM_INT);
        $event->bindValue(':activation_id', $activationId, $activationId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $event->bindValue(':event_type', $eventType);
        $event->bindValue(':reason_code', $reasonCode);
        $event->bindValue(':correlation_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $event->bindValue(':occurred_at', $occurredAt);
        $event->execute();
    }

    private static function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || !ctype_digit($value)) {
            throw new \UnexpectedValueException('Privileged-access maintenance candidate is invalid.');
        }

        return (int) $value;
    }

    private static function string(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \UnexpectedValueException('Privileged-access maintenance candidate is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function nullableUuid(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;

        return $value === null ? null : UuidV7::fromBinary(self::string($value))->toString();
    }

    private function schemaIsAvailable(PDO $pdo): bool
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() '
            . "AND table_name IN ('privileged_access_requests','privileged_access_activations','privileged_access_reviews')",
        );
        $statement->execute();
        $value = $statement->fetchColumn();

        return (is_int($value) || is_string($value) && ctype_digit($value)) && (int) $value === 3;
    }
}
